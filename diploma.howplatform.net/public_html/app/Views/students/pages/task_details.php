<?php
/** صفحة تفاصيل المهمة — تُحمَّل ديناميكيًا عبر student_router */
?>
<?php
/**
 * students/pages/task_details.php
 * يُحمّل داخل student_router
 */

// اجعل $user موجود دائماً (fallback من auth() أو session)
if (!isset($user) || !$user) {
    if (function_exists('auth') && auth()?->user()) {
        $au   = auth()->user();
        $user = (object)[
            'id'    => (int)($au->id ?? 0),
            'name'  => (string)($au->name ?? ''),
            'email' => (string)($au->email ?? ''),
        ];
    } else {
        $user = (object)[
            'id'    => (int)(session('user_id') ?? 0),
            'name'  => (string)(session('user_name') ?? ''),
            'email' => (string)(session('user_email') ?? ''),
        ];
    }
}
$userId = (int)($user->id ?? 0);
?>

<section id="task-details" class="task-details-page">
  <!-- 🛰️ Blue Sleek Header: الهيدر الأزرق الأنيق -->
  <header class="td-header-compact-blue">
    <div class="td-header-main">
      <button id="td-back-week" class="btn-compact-back-blue">
        <i class="fas fa-arrow-right"></i>
        <span>العودة</span>
      </button>
      <div class="td-title-area">
        <h1 id="td-title" class="td-compact-title">...</h1>
        <div id="td-meta" class="td-compact-meta"></div>
      </div>
    </div>
    <div class="td-header-nav">
      <button id="td-prev" class="btn-nav-compact-blue" disabled><i class="fas fa-chevron-right"></i></button>
      <button id="td-next" class="btn-nav-compact-blue" disabled><i class="fas fa-chevron-left"></i></button>
    </div>
  </header>

  <div class="td-workspace-grid">
    <!-- 🚀 Main Content -->
    <main class="td-content-area">
      
      <!-- ✨ AI Panel -->
      <div class="premium-card ai-workspace-card" id="ai-actions-card">
        <div class="card-header-premium">
          <div class="icon-box-ai"><i class="fas fa-wand-magic-sparkles"></i></div>
          <h3>مساعد الذكاء الاصطناعي</h3>
        </div>
        <div id="ai-tools-panel" class="ai-tools-panel">
          <div class="ai-tools-empty-state">
            <div class="pulse-loader"></div>
          </div>
        </div>
      </div>
      <div id="ai-tools-modals-root"></div>

      <!-- 📝 Task Description -->
      <div class="premium-card content-card">
        <div class="card-header-premium">
          <div class="icon-box"><i class="fas fa-file-lines"></i></div>
          <h3>وصف المهمة</h3>
        </div>
        <div id="td-desc" class="td-rich-content"></div>
      </div>

      <!-- 🖼️ المرفقات (أسفل الوصف) -->
      <div id="td-media" class="premium-card content-card media-card" hidden>
        <div class="card-header-premium">
          <div class="icon-box"><i class="fas fa-paperclip"></i></div>
          <h3>المرفقات</h3>
        </div>
        <div id="td-media-grid" class="media-grid-modern"></div>
      </div>

      <!-- 📎 Attached Files -->
      <div id="td-files" class="premium-card files-card" hidden>
        <div class="card-header-premium">
          <div class="icon-box"><i class="fas fa-paperclip"></i></div>
          <h3>الملفات</h3>
        </div>
        <div id="td-files-list" class="files-list-modern"></div>
      </div>

      <!-- 🔒 Upgrade CTA -->
      <div id="td-upgrade-cta" class="premium-card upgrade-card-premium" hidden>
        <div class="upgrade-glass">
          <div class="upgrade-icon"><i class="fas fa-crown"></i></div>
          <div class="upgrade-text">
            <h4>محتوى للمشتركين</h4>
          </div>
          <button id="td-go-upgrade" class="btn-premium-action">
            <span>ترقية</span>
          </button>
        </div>
      </div>
    </main>

    <!-- 📊 Sidebar -->
    <aside class="td-sidebar-modern">
      
      <!-- ⏰ Dates Widget (فوق مهام الأسبوع) -->
      <div class="premium-card sidebar-widget">
        <div class="card-header-premium">
          <div class="icon-box-dates"><i class="fas fa-calendar-check"></i></div>
          <h3>المواعيد</h3>
        </div>
        <div id="td-dates" class="dates-list-modern">
          <!-- سيتم حقنه بواسطة JS -->
        </div>
      </div>

      <!-- 🗺️ Roadmap (مهام الأسبوع) -->
      <div class="premium-card sidebar-widget">
        <div class="card-header-premium">
          <div class="icon-box-roadmap"><i class="fas fa-layer-group"></i></div>
          <h3 id="td-week-name">الأسبوع</h3>
        </div>
        <div id="td-siblings" class="roadmap-list-modern">
          <!-- سيتم حقنه بواسطة JS -->
        </div>
      </div>

      <!-- 📢 Task Details Sidebar Ad -->
      <div class="premium-card sidebar-widget td-ad-widget" style="padding: 15px; text-align: center; overflow: hidden; display: flex; align-items: center; justify-content: center;">
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3572625518315562" crossorigin="anonymous"></script>
        <!-- TaskDetailsAd -->
        <ins class="adsbygoogle"
             style="display:block; width: 100%;"
             data-ad-client="ca-pub-3572625518315562"
             data-ad-slot="2980803002"
             data-ad-format="auto"
             data-full-width-responsive="true"></ins>
        <script>
             (adsbygoogle = window.adsbygoogle || []).push({});
        </script>
      </div>
    </aside>
  </div>

  <!-- 🔍 PDF Modal -->
  <div id="pdfModal" class="glass-modal" aria-hidden="true">
    <div class="modal-blur-bg" data-close-pdf></div>
    <div class="modal-content-wrap">
      <div class="modal-header">
        <h4 id="pdfTitleDisplay">...</h4>
        <button class="modal-close-btn" data-close-pdf><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-body">
        <iframe id="pdfFrame" frameborder="0"></iframe>
      </div>
    </div>
  </div>
</section>

<style>
:root {
  --p-radius: 12.8px;
  --p-radius-sm: 9.6px;
  --blue-primary: #219ebc;
  --blue-dark: #023047;
  --header-bg: #ffffff;
  --surface-0: #ffffff;
  --surface-1: #f4f6f9;
  --text-strong: #0a3247;
  --text-muted: #6b7280;
  --border-color: #e7eaee;
  --ai-gradient: linear-gradient(135deg, #219ebc 0%, #1b81a0 100%);
}

[data-theme="dark"] {
  --header-bg: #1e293b;
  --surface-0: #1e293b;
  --surface-1: #0f172a;
  --text-strong: #f1f5f9;
  --text-muted: #94a3b8;
  --border-color: #334155;
}

.task-details-page {
  display: flex;
  flex-direction: column;
  gap: 19.2px;
  padding: 19.2px;
}

/* 🛰️ Blue Header */
.td-header-compact-blue {
  background: var(--header-bg);
  color: var(--text-strong);
  border: 0.8px solid var(--border-color);
  border-radius: var(--p-radius);
  padding: 11.2px 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: none;
}

.td-header-main {
  display: flex;
  align-items: center;
  gap: 16px;
}

.btn-compact-back-blue {
  background: var(--surface-1);
  border: 0.8px solid var(--border-color);
  color: var(--text-strong);
  padding: 6.4px 12.8px;
  border-radius: 799.2px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6.4px;
  font-weight: 700;
  transition: all .16s ease;
}
.btn-compact-back-blue:hover { background: var(--surface-0); border-color: var(--blue-primary); color: var(--blue-primary); }

.td-compact-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--text-strong);
}

.td-compact-meta {
  font-size: 0.9rem;
  color: var(--text-muted);
}

.td-header-nav {
  display: flex;
  gap: 6.4px;
}

.btn-nav-compact-blue {
  width: 32px;
  height: 32px;
  border-radius: 799.2px;
  background: var(--surface-1);
  border: 0.8px solid var(--border-color);
  color: var(--text-muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all .16s ease;
}

.btn-nav-compact-blue:hover:not(:disabled) {
  background: var(--surface-0);
  border-color: var(--blue-primary);
  color: var(--blue-primary);
}
.btn-nav-compact-blue:disabled { opacity: .45; cursor: default; }

/* 🏗️ Grid & Layout - Expanded Dimensions */
.td-workspace-grid {
  display: grid;
  grid-template-columns: 1fr 288px;
  gap: 17.6px;
  align-items: start;
}

.td-content-area {
  display: flex;
  flex-direction: column;
  gap: 14.4px;
}

.td-sidebar-modern {
  display: flex;
  flex-direction: column;
  gap: 14.4px;
}

.premium-card {
  background: var(--surface-0);
  border: 0.8px solid var(--border-color);
  border-radius: var(--p-radius);
  padding: 16px;
}

.sidebar-widget {
  padding: 16px;
}

.card-header-premium {
  display: flex;
  align-items: center;
  gap: 10.4px;
  margin-bottom: 14.4px;
}

.card-header-premium h3 {
  margin: 0;
  font-size: 1.12rem;
  font-weight: 800;
  color: var(--text-strong);
}

.icon-box, .icon-box-ai, .icon-box-dates, .icon-box-roadmap {
  width: 33.6px;
  height: 33.6px;
  border-radius: 9.6px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
}

.icon-box { background: var(--surface-1); color: var(--text-muted); }
.icon-box-ai { background: var(--ai-gradient); color: #fff; }
.icon-box-dates { background: #fff3d6; color: #d38202; }
.icon-box-roadmap { background: #e8f5fb; color: #1b81a0; }

/* 🗺️ Roadmap List */
.roadmap-list-modern {
  display: flex;
  flex-direction: column;
  gap: 9.6px;
}

.td-task-chip {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12.8px;
  border-radius: var(--p-radius-sm);
  background: var(--surface-1);
  border: 0.8px solid var(--border-color);
  cursor: pointer;
  transition: all 0.2s ease;
  width: 100%;
  text-align: right;
  font-weight: 700;
  color: var(--text-strong);
  font-size: 1rem;
}

.td-task-chip:hover { border-color: var(--blue-primary); transform: translateX(-4px); }
.td-task-chip.active { background: #e0f2fe; border-color: #7dd3fc; color: #0369a1; }

/* ⏰ Dates List */
.dates-list-modern .td-meta-list {
  list-style: none; padding: 0; margin: 0;
  display: flex; flex-direction: column; gap: 9.6px;
}

.dates-list-modern .td-meta-list li {
  padding: 12px 16px;
  border-radius: var(--p-radius-sm);
  background: var(--surface-1);
  border: 0.8px solid var(--border-color);
  display: flex;
  flex-direction: column;
  gap: 3.2px;
}

.dates-list-modern .td-meta-list li strong { font-size: 0.85rem; color: var(--text-muted); }
.dates-list-modern .td-meta-list li span { font-size: 1.1rem; font-weight: 800; color: var(--text-strong); }

/* 🖼️ Expanded Media Grid */
.media-grid-modern {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(192px, 1fr));
  gap: 16px;
}

.media-item-wrap {
  border-radius: var(--p-radius);
  overflow: hidden;
  aspect-ratio: 16/10; 
  cursor: pointer;
  border: 0.8px solid var(--border-color);
  transition: all 0.3s ease;
  background: var(--surface-1);
}

.media-item-wrap:hover { transform: scale(1.03); border-color: var(--blue-primary); }
.media-item-wrap img, .media-item-wrap video { width: 100%; height: 100%; object-fit: cover; }

/* 📎 Expanded Files Design */
.files-list-modern {
  display: flex;
  flex-direction: column;
  gap: 9.6px;
}

.td-file {
  display: flex;
  align-items: center;
  gap: 12.8px;
  padding: 12.8px 16px;
  background: var(--surface-1);
  border: 0.8px solid var(--border-color);
  border-radius: var(--p-radius-sm);
  transition: all 0.2s ease;
}

.td-file:hover { background: var(--surface-0); border-color: var(--blue-primary); transform: translateX(-4px); }

.td-file .icon {
  font-size: 1.4rem; color: var(--blue-primary); background: #fff;
  width: 38.4px; height: 38.4px; display: flex; align-items: center; justify-content: center;
  border-radius: 9.6px; box-shadow: 0 3.2px 8px rgba(0,0,0,0.05);
}

.td-file .grow { flex: 1; min-width: 0; }
.td-file .name { font-weight: 800; font-size: 1rem; color: var(--text-strong); }
.td-file .meta { font-size: 0.85rem; color: var(--text-muted); }

.td-file .btn-dl, .td-file .btn-view {
  padding: 8px 14.4px; border-radius: 6.4px; font-weight: 700; font-size: 0.9rem;
  border: 0.8px solid var(--border-color); background: #fff; color: var(--text-strong);
}

/* Modal */
.glass-modal { position: fixed; inset: 0; z-index: 9999; display: none; align-items: center; justify-content: center; }
.glass-modal[aria-hidden="false"] { display: flex; }
.modal-blur-bg { position: absolute; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(6.4px); }
.modal-content-wrap { position: relative; width: 90%; height: 90%; background: var(--surface-0); border-radius: var(--p-radius); overflow: hidden; display: flex; flex-direction: column; }
.modal-header { padding: 16px; border-bottom: 0.8px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
#pdfFrame { width: 100%; height: 100%; }

/* 📝 وصف المهمة: تسطيح الصندوق الداخلي + قصّ مع زر "عرض المزيد" */
.td-rich-content .td-desc-body {
  background: transparent; border: none; box-shadow: none; padding: 0;
  color: var(--text-strong); font-size: 0.98rem; line-height: 1.9; position: relative;
}
.td-rich-content .td-desc-body p { margin: 0 0 9.6px; }
.td-rich-content .td-desc-body p:last-child { margin-bottom: 0; }
.td-rich-content .td-desc-body a { color: var(--blue-primary); text-decoration: none; }
.td-rich-content .td-desc-body a:hover { text-decoration: underline; }
.td-rich-content .td-desc-body.td-clamp { max-height: 9.5em; overflow: hidden; }
.td-rich-content .td-desc-body.td-clamp::after {
  content: ""; position: absolute; inset-inline: 0; bottom: 0; height: 36.8px;
  background: linear-gradient(to top, var(--surface-0), transparent); pointer-events: none;
}
.td-rich-content .td-desc-body.expanded { max-height: none; }
.td-rich-content .td-desc-body.expanded::after { display: none; }
.td-desc-toggle {
  margin-top: 9.6px; display: none; align-items: center; gap: 4.8px;
  background: var(--surface-1); border: 0.8px solid var(--border-color); color: var(--blue-primary);
  font-family: inherit; font-weight: 700; font-size: 0.86rem; padding: 6.4px 12.8px;
  border-radius: 799.2px; cursor: pointer; transition: all .16s ease;
}
.td-desc-toggle:hover { background: var(--surface-0); border-color: var(--blue-primary); }
.td-desc-toggle.show { display: inline-flex; }

/* ===================== لغة NotebookLM الكاملة على الشاشة المحورية ===================== */
/* الرقائق بجانب عنوان المهمة (لا تحته) */
.td-title-area { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.td-compact-meta { display: flex; gap: 6.4px; flex-wrap: wrap; align-items: center; }
.td-chip { display: inline-flex; align-items: center; gap: 4.8px; font-size: .78rem; font-weight: 700;
  padding: 4px 9.6px; border-radius: 799.2px; background: var(--surface-1); color: var(--text-muted); border: 0.8px solid var(--border-color); }
.td-chip-type { background: #e8f5fb; color: #156683; border-color: transparent; }
.td-chip-due  { background: #fff3d6; color: #b45309; border-color: transparent; }

/* أزرار حبوبية داخل صفحة المهمة (تتجاوز أزرار مصنع الأدوات والملفات) */
.task-details-page .btn { display: inline-flex; align-items: center; gap: 6.4px; font-family: inherit;
  font-weight: 700; font-size: .88rem; border-radius: 799.2px; padding: 8px 14.4px; cursor: pointer;
  border: 0.8px solid transparent; transition: all .16s ease; line-height: 1.2; }
.task-details-page .btn-primary { background: var(--blue-primary); color: #fff; }
.task-details-page .btn-primary:hover { background: #1b81a0; }
.task-details-page .btn-secondary { background: #e8f5fb; color: #156683; }
.task-details-page .btn-secondary:hover { background: #d2ebf7; }

/* بطاقات الأدوات → فلات NotebookLM */
.task-details-page .ai-tool-card { border: 0.8px solid var(--border-color); border-radius: 12.8px; padding: 14.4px;
  background: var(--surface-0); box-shadow: none; transition: all .18s ease; }
.task-details-page .ai-tool-card:hover { border-color: #8ecae6; box-shadow: 0 1.6px 8px rgba(16,24,40,.06); }
.task-details-page .ai-tool-title { color: var(--text-strong); font-weight: 800; }
.task-details-page .ai-tool-desc { color: var(--text-muted); }
.task-details-page .ai-tool-status { border-radius: 799.2px; box-shadow: none; font-weight: 700; border: 0.8px solid transparent; }
.task-details-page .ai-tool-status.processing { background: #fff3d6; color: #b45309; }
.task-details-page .ai-tool-status.pending { background: #e8f1ff; color: #2563eb; }
.task-details-page .ai-tool-status.done { background: #e7f7ee; color: #15803d; }
.task-details-page .ai-tool-status.failed { background: #fdeaea; color: #b91c1c; }
.task-details-page .ai-tools-empty { border-radius: 11.2px; }

/* الملفات كصفوف "مصادر" هادئة */
.td-file { transition: all .16s ease; }
.td-file:hover { transform: none; border-color: #8ecae6; }
.td-file .icon { background: #e8f5fb; color: #1b81a0; box-shadow: none; border-radius: 9.6px; }
.td-file .btn-dl, .td-file .btn-view { border-radius: 799.2px; background: #e8f5fb; color: #156683; border-color: transparent; font-weight: 700; }
.td-task-chip:hover { transform: none; border-color: #8ecae6; }

/* تهدئة الوضع الداكن */
[data-theme="dark"] .td-chip-type { background: #0e2230; color: #8ecae6; }
[data-theme="dark"] .td-chip-due  { background: #2a2310; color: #ffbe34; }
[data-theme="dark"] .task-details-page .btn-secondary { background: #0e2230; color: #8ecae6; }
[data-theme="dark"] .td-file .icon { background: #0e2230; color: #8ecae6; }
[data-theme="dark"] .task-details-page .ai-tool-status.processing { background: #2a2310; color: #fbbf24; }
[data-theme="dark"] .task-details-page .ai-tool-status.done { background: #0f2a1c; color: #4ade80; }

/* مهام الأسبوع: خريطة طريق بنقاط حالة */
.roadmap-list-modern { gap: 1.6px; }
.td-task-chip { display: flex; align-items: center; gap: 8.8px; padding: 8.8px 9.6px; border-radius: 9.6px;
  background: transparent; border: 0.8px solid transparent; font-weight: 700; font-size: .9rem; color: var(--text-strong); transition: all .14s ease; }
.td-task-chip:hover { background: var(--surface-1); border-color: transparent; transform: none; }
.td-task-chip.active { background: #e8f5fb; border-color: transparent; color: #156683; }
.td-step-dot { width: 8.8px; height: 8.8px; border-radius: 50%; border: 1.6px solid var(--border-strong); flex-shrink: 0; }
.td-task-chip.active .td-step-dot { border-color: #219ebc; background: #219ebc; }
.td-step-label { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
[data-theme="dark"] .td-task-chip.active { background: #0e2230; color: #8ecae6; }

/* المرفقات في الرِّيل: عمود واحد */
.td-sidebar-modern .media-grid-modern { grid-template-columns: 1fr; }

/* ===== تصميم بطاقة الأداة: أبسط + أزرار أيقونية + حالة واحدة ===== */
.task-details-page .ai-tool-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 11.2px; }
.task-details-page .ai-tool-card-head > div:first-child { flex: 1; min-width: 0; }
.task-details-page .ai-tool-card-head .ai-tool-title { font-size: 1.05rem; }
/* زر التشغيل في الترويسة (يبقى دائماً لإرسال أكثر من طلب) */
.task-details-page .ai-tool-card-head .btn-primary { flex-shrink: 0; white-space: nowrap; }
/* شريط الحالة + الأزرار الأيقونية أسفل النتيجة */
.task-details-page .ai-tool-actions { display: flex; gap: 6.4px; flex-wrap: wrap; align-items: center; margin-top: 3.2px; }
.task-details-page .ai-tool-card > .ai-tool-actions:last-of-type { margin-top: 0; }
/* أزرار أيقونية دائرية بلا كلمات (تنزيل / معاينة / إعادة توليد / حذف) */
.task-details-page .ait-icon-btn { width: 32px; height: 32px; min-width: 32px; padding: 0; border-radius: 799.2px;
  display: inline-flex; align-items: center; justify-content: center; font-size: 0; line-height: 0; }
.task-details-page .ait-icon-btn i { font-size: 1rem; }
.task-details-page .ai-tool-delete-btn.ait-icon-btn { background: #fdeaea; color: #b91c1c; border: 0.8px solid transparent; }
[data-theme="dark"] .task-details-page .ai-tool-delete-btn.ait-icon-btn { background: #3a1414; color: #f87171; }

/* ===== المكوّن الموحّد لعرض النتائج (سجل النتائج) ===== */
.task-details-page .ai-job-item { border: 0.8px solid var(--border-color); border-radius: 11.2px; padding: 0; overflow: hidden; box-shadow: none; cursor: default; background: var(--surface-0); }
.task-details-page .ai-job-item:hover { transform: none; box-shadow: none; border-color: var(--border-color); }
.task-details-page .ai-job-item.active { border-color: var(--blue-primary); background: var(--surface-0); box-shadow: none; }
.task-details-page .ai-job-item-header { padding: 8.8px 11.2px; margin-bottom: 0; background: var(--surface-1); border-bottom: 0.8px solid var(--border-color); align-items: center; }
.task-details-page .ai-job-item-num { color: var(--text-strong); }
.task-details-page .ai-job-item-status { border-radius: 799.2px; box-shadow: none; }
.task-details-page .ai-job-item-date { font-size: .78rem; }
/* أزرار العنصر → أيقونية موحّدة (تتجاوز الستايل المضمّن) */
.task-details-page .ai-job-item-actions .btn { font-size: 0 !important; width: 28.8px !important; height: 28.8px !important; min-width: 28.8px; padding: 0 !important;
  border-radius: 799.2px !important; display: inline-flex !important; align-items: center; justify-content: center;
  background: var(--surface-0) !important; color: var(--text-muted) !important; border: 0.8px solid var(--border-color) !important; box-shadow: none !important; }
.task-details-page .ai-job-item-actions .btn:hover { border-color: var(--blue-primary) !important; color: var(--blue-primary) !important; transform: none !important; }
.task-details-page .ai-job-item-actions .btn i { font-size: .9rem !important; color: inherit !important; }
.task-details-page .ai-job-item-actions .btn span { display: none !important; }
/* المعاينة الموحّدة */
.ai-job-preview { padding: 11.2px; }
.ai-job-thumb { display: block; max-width: 224px; border: 0.8px solid var(--border-color); border-radius: 8px; overflow: hidden; background: var(--surface-1); }
.ai-job-thumb img { display: block; width: 100%; height: auto; max-height: 240px; object-fit: contain; }
.ai-job-files { display: flex; flex-direction: column; gap: 6.4px; }
.ai-job-file { display: inline-flex; align-items: center; gap: 7.2px; padding: 8px 10.4px; border: 0.8px solid var(--border-color); border-radius: 8px;
  background: var(--surface-1); font-weight: 700; font-size: .86rem; color: var(--text-strong); text-decoration: none; transition: all .16s ease; }
.ai-job-file:hover { border-color: var(--blue-primary); color: var(--blue-primary); }
.ai-job-file i { color: var(--blue-primary); }
.ai-job-snippet { font-size: .9rem; line-height: 1.85; color: var(--text); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

/* ===== عرض النتيجة داخل بطاقة الأداة (inline) ===== */
.task-details-page .ai-tool-result-inline { margin-top: 11.2px; padding-top: 11.2px; border-top: 0.8px dashed var(--border-color); }
.ai-inline-loading { display: flex; align-items: center; gap: 6.4px; color: var(--text-muted); font-size: .88rem; }
.ai-inline-imgwrap { display: block; border: 0.8px solid var(--border-color); border-radius: 9.6px; overflow: hidden; background: var(--surface-1); }
.ai-inline-imgwrap img { display: block; width: 100%; height: auto; max-height: 352px; object-fit: contain; }
.ai-inline-actions { display: flex; gap: 6.4px; flex-wrap: wrap; margin-top: 8px; }
.ai-inline-files { display: flex; flex-direction: column; gap: 6.4px; }
.ai-inline-file { display: inline-flex; align-items: center; gap: 7.2px; padding: 8.8px 11.2px; border: 0.8px solid var(--border-color);
  border-radius: 9.6px; background: var(--surface-1); color: var(--text-strong); font-weight: 700; font-size: .88rem; text-decoration: none; transition: all .16s ease; }
.ai-inline-file:hover { border-color: var(--blue-primary); color: var(--blue-primary); }
.ai-inline-file i { color: var(--blue-primary); }
.ai-inline-field { margin-bottom: 11.2px; }
.ai-inline-field:last-child { margin-bottom: 0; }
.ai-inline-field h5 { margin: 0 0 4px; color: var(--blue-primary); font-size: .92rem; font-weight: 800; }
.ai-inline-field > div { color: var(--text-strong); font-size: .94rem; line-height: 1.85; }

@media (max-width: 1100px) {
  .td-workspace-grid { grid-template-columns: 1fr; }
}
</style>
