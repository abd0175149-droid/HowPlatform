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

      <!-- 🖼️ Media Gallery -->
      <div id="td-media" class="premium-card media-card" hidden>
        <div class="card-header-premium">
          <div class="icon-box"><i class="fas fa-photo-film"></i></div>
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
      
      <!-- 🗺️ Roadmap -->
      <div class="premium-card sidebar-widget">
        <div class="card-header-premium">
          <div class="icon-box-roadmap"><i class="fas fa-layer-group"></i></div>
          <h3 id="td-week-name">الأسبوع</h3>
        </div>
        <div id="td-siblings" class="roadmap-list-modern">
          <!-- سيتم حقنه بواسطة JS -->
        </div>
      </div>

      <!-- ⏰ Dates Widget -->
      <div class="premium-card sidebar-widget">
        <div class="card-header-premium">
          <div class="icon-box-dates"><i class="fas fa-calendar-check"></i></div>
          <h3>المواعيد</h3>
        </div>
        <div id="td-dates" class="dates-list-modern">
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
  --p-radius: 12px;
  --p-radius-sm: 8px;
  --blue-primary: #219ebc;
  --blue-dark: #023047;
  --header-bg: linear-gradient(135deg, #219ebc 0%, #023047 100%);
  --surface-0: #ffffff;
  --surface-1: #f8fafc;
  --text-strong: #0f172a;
  --text-muted: #64748b;
  --border-color: #e2e8f0;
  --ai-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
}

[data-theme="dark"] {
  --surface-0: #1e293b;
  --surface-1: #0f172a;
  --text-strong: #f1f5f9;
  --text-muted: #94a3b8;
  --border-color: #334155;
}

.task-details-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  padding: 24px;
}

/* 🛰️ Blue Header */
.td-header-compact-blue {
  background: var(--header-bg);
  color: #fff;
  border-radius: var(--p-radius);
  padding: 14px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 4px 12px rgba(2, 48, 71, 0.15);
}

.td-header-main {
  display: flex;
  align-items: center;
  gap: 20px;
}

.btn-compact-back-blue {
  background: rgba(255,255,255,0.15);
  border: 1px solid rgba(255,255,255,0.2);
  color: #fff;
  padding: 8px 16px;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
}

.td-compact-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 800;
}

.td-compact-meta {
  font-size: 0.9rem;
  opacity: 0.8;
}

.td-header-nav {
  display: flex;
  gap: 8px;
}

.btn-nav-compact-blue {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  background: rgba(255,255,255,0.15);
  border: 1px solid rgba(255,255,255,0.2);
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-nav-compact-blue:hover:not(:disabled) {
  background: #fff;
  color: var(--blue-dark);
}

/* 🏗️ Grid & Layout - Expanded Dimensions */
.td-workspace-grid {
  display: grid;
  grid-template-columns: 1fr 420px; /* زيادة عرض الشريط الجانبي */
  gap: 40px; 
  align-items: start;
}

.td-content-area {
  display: flex;
  flex-direction: column;
  gap: 30px;
}

.td-sidebar-modern {
  display: flex;
  flex-direction: column;
  gap: 40px;
}

.premium-card {
  background: var(--surface-0);
  border: 1px solid var(--border-color);
  border-radius: var(--p-radius);
  padding: 24px;
}

.sidebar-widget {
  padding: 24px;
}

.card-header-premium {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 24px;
}

.card-header-premium h3 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 900;
  color: var(--text-strong);
}

.icon-box, .icon-box-ai, .icon-box-dates, .icon-box-roadmap {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
}

.icon-box { background: #f1f5f9; color: #475569; }
.icon-box-ai { background: var(--ai-gradient); color: #fff; }
.icon-box-dates { background: #fff1f2; color: #e11d48; }
.icon-box-roadmap { background: #f0f9ff; color: #0369a1; }

/* 🗺️ Roadmap List */
.roadmap-list-modern {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.td-task-chip {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 16px;
  border-radius: var(--p-radius-sm);
  background: var(--surface-1);
  border: 1px solid var(--border-color);
  cursor: pointer;
  transition: all 0.2s ease;
  width: 100%;
  text-align: right;
  font-weight: 700;
  color: var(--text-strong);
  font-size: 1rem;
}

.td-task-chip:hover { border-color: var(--blue-primary); transform: translateX(-5px); }
.td-task-chip.active { background: #e0f2fe; border-color: #7dd3fc; color: #0369a1; }

/* ⏰ Dates List */
.dates-list-modern .td-meta-list {
  list-style: none; padding: 0; margin: 0;
  display: flex; flex-direction: column; gap: 12px;
}

.dates-list-modern .td-meta-list li {
  padding: 15px 20px;
  border-radius: var(--p-radius-sm);
  background: var(--surface-1);
  border: 1px solid var(--border-color);
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.dates-list-modern .td-meta-list li strong { font-size: 0.85rem; color: var(--text-muted); }
.dates-list-modern .td-meta-list li span { font-size: 1.1rem; font-weight: 800; color: var(--text-strong); }

/* 🖼️ Expanded Media Grid */
.media-grid-modern {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 20px;
}

.media-item-wrap {
  border-radius: var(--p-radius);
  overflow: hidden;
  aspect-ratio: 16/10; 
  cursor: pointer;
  border: 1px solid var(--border-color);
  transition: all 0.3s ease;
  background: var(--surface-1);
}

.media-item-wrap:hover { transform: scale(1.03); border-color: var(--blue-primary); }
.media-item-wrap img, .media-item-wrap video { width: 100%; height: 100%; object-fit: cover; }

/* 📎 Expanded Files Design */
.files-list-modern {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.td-file {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 20px;
  background: var(--surface-1);
  border: 1px solid var(--border-color);
  border-radius: var(--p-radius-sm);
  transition: all 0.2s ease;
}

.td-file:hover { background: var(--surface-0); border-color: var(--blue-primary); transform: translateX(-5px); }

.td-file .icon {
  font-size: 1.4rem; color: var(--blue-primary); background: #fff;
  width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;
  border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}

.td-file .grow { flex: 1; min-width: 0; }
.td-file .name { font-weight: 800; font-size: 1rem; color: var(--text-strong); }
.td-file .meta { font-size: 0.85rem; color: var(--text-muted); }

.td-file .btn-dl, .td-file .btn-view {
  padding: 10px 18px; border-radius: 8px; font-weight: 700; font-size: 0.9rem;
  border: 1px solid var(--border-color); background: #fff; color: var(--text-strong);
}

/* Modal */
.glass-modal { position: fixed; inset: 0; z-index: 9999; display: none; align-items: center; justify-content: center; }
.glass-modal[aria-hidden="false"] { display: flex; }
.modal-blur-bg { position: absolute; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); }
.modal-content-wrap { position: relative; width: 90%; height: 90%; background: var(--surface-0); border-radius: var(--p-radius); overflow: hidden; display: flex; flex-direction: column; }
.modal-header { padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
#pdfFrame { width: 100%; height: 100%; }

@media (max-width: 1100px) {
  .td-workspace-grid { grid-template-columns: 1fr; }
}
</style>
