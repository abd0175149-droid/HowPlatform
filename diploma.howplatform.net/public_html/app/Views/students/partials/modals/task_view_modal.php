<?php
// app/Views/student/partials/modals/task_view_modal.php
// ✅ Modal: Task View — HTML + CSS — أسماء الكلاسات مطابقة لطلبك
?>
<div class="modal-overlay" id="viewModalOverlay" role="dialog" aria-modal="true" aria-hidden="true" dir="rtl">
  <div class="view-modal">
    <div class="modal-header">
      <div class="modal-header-content">
        <h2>
          <i class="fas fa-tasks" aria-hidden="true"></i>
          <span id="viewTaskTitle">-</span>
        </h2>

        <!-- معلومات موجزة ضمن الهيدر (مطابقة للأسماء التي زوّدتني بها) -->
        <div class="secondary-info-grid-header">
          <div class="info-item-header">
            <i class="fas fa-university" aria-hidden="true"></i>
            <span class="value" id="viewUniversity">-</span>
          </div>
          <div class="info-item-header">
            <i class="fas fa-book" aria-hidden="true"></i>
            <span class="value" id="viewCourse">-</span>
          </div>
          <div class="info-item-header">
            <i class="fas fa-calendar-week" aria-hidden="true"></i>
            <span class="value" id="viewWeek">-</span>
          </div>
          <div class="info-item-header">
            <i class="fas fa-clipboard-list" aria-hidden="true"></i>
            <span class="value" id="viewTaskType">-</span>
          </div>
          <div class="info-item-header">
            <i class="far fa-calendar-alt" aria-hidden="true"></i>
            <span class="value" id="viewDueDateValue">-</span>
          </div>
        </div>
      </div>

      <!-- زر إغلاق علوي -->
      <button class="close-modal" id="viewCloseBtn" aria-label="إغلاق">
        <i class="fas fa-times" aria-hidden="true"></i>
      </button>
    </div>

    <div class="modal-body">
      <!-- الوصف -->
      <section class="description-section">
        <h3><i class="fas fa-align-left" aria-hidden="true"></i> وصف المهمة</h3>
        <p id="viewDescription">—</p>
      </section>

      <!-- الملفات -->
      <section class="files-section">
        <h3 class="section-title"><i class="fas fa-paperclip" aria-hidden="true"></i> الملفات</h3>
        <div class="files-container" id="viewFilesContainer">
          <p class="muted">لا توجد ملفات مرفقة.</p>
        </div>
      </section>

      <!-- فوتر -->
      <div class="modal-footer">
        <button class="action-btn close-btn-task" id="viewModalCloseFooter">
          <i class="fas fa-check" aria-hidden="true"></i> تم
        </button>
      </div>
    </div>
  </div>
</div>

<style>
/* =========================
   Task View Modal — Extract
   ========================= */
/* 1) غلاف الأوفرلاي: تأكيد ملء الشاشة + تمركز قوي */
.modal-overlay{
  position: fixed;
  inset: 0;
  background-color: rgba(0,0,0,0.7);

  /* Grid للتمركز */
  display: grid;
  place-items: center;      /* يوسّط العنصر محورياً وعمودياً */
  place-content: center;    /* يحافظ على التمركز حتى مع المساحات الإضافية */

  /* ضمان الارتفاع على كل المتصفحات (خاصة الجوال) */
  min-height: 100vh;
  min-height: 100dvh;       /* وحدات viewport الحديثة */

  /* إبقاء نظام الإظهار/الإخفاء كما هو عندك */
  z-index: 1002;
  opacity: 0;
  visibility: hidden;
  pointer-events: none;
  transition: var(--transition);

  /* هام: مسافة حماية بسيطة لمنع الالتصاق بالحواف عندما يكون المحتوى طويل */
  padding: clamp(8px, 2vh, 24px);
}
.modal-overlay.active,
.modal-overlay.show{
  opacity: 1;
  visibility: visible;
  pointer-events: auto;
}

/* 2) صندوق المودال: احسب الارتفاع بنظام border-box ووسّطه ذاتياً أيضاً */
.view-modal{
  background: var(--surface-0);
  border-radius: 16px;
  width: 90vh;
  /* احسب أقصى ارتفاع وفق viewport الحديث + لا تتجاوز padding الأوفرلاي */
  box-sizing: border-box;
  max-height: min(90dvh, 90vh);
  overflow-y: auto;
  /* حركة الدخول */
  opacity: 0;
  transform: translateY(30px) scale(0.98);
  transition: var(--transition);
  scrollbar-width: none;

  /* تأكيد التمركز داخل Grid حتى لو وُجدت قواعد عامة */
  place-self: center;
  margin: 0; /* إلغاء أي margin عام قد يدفعه للأعلى */
}

/* عند الفتح */
.modal-overlay.active .view-modal,
.modal-overlay.show  .view-modal{
  transform: translateY(0) scale(1);
  opacity: 1;
}


/* Header */
.view-modal .modal-header{
  background:linear-gradient(135deg, var(--primary-900), var(--primary-700));
  color:#fff;
  padding:25px;
  display:flex; justify-content:space-between; align-items:center;
  position:relative; z-index:2;
  border-bottom:1px solid rgba(255,255,255,.15);
}
.view-modal .modal-header::before{
  content:""; position:absolute; top:0; right:0; width:100%; height:100%;
  opacity:.18; z-index:-1;
  background:radial-gradient(1000px 200px at 80% -50%, rgba(255,255,255,.2), transparent);
}
.view-modal .modal-header h2{
  font-size:1.8rem; display:flex; align-items:center; gap:15px; margin-bottom:10px;
}
.view-modal .modal-header h2 i{
  font-size:1.5rem; color:var(--accent-500);
}

/* زر الإغلاق */
.view-modal .close-modal{
  background:rgba(255,255,255,.2); border:none; color:#fff;
  font-size:1.5rem; cursor:pointer; width:45px; height:45px;
  border-radius:50%; display:flex; align-items:center; justify-content:center;
  transition:var(--transition); flex-shrink:0;
}
.view-modal .close-modal:hover{
  background:rgba(255,255,255,.3);
  transform:rotate(90deg);
}

/* شريط معلومات سريع داخل الهيدر */
.secondary-info-grid-header{ display:flex; gap:15px; }
.info-item-header{
  display:flex; align-items:center; gap:10px; padding:8px 0; color:var(--text-inverse);
}
.info-item-header i{ color:#fff; font-size:1.1rem; width:24px; text-align:center; }
.info-item-header .value{ font-weight:600; color:#eef; }

/* Body */
.view-modal .modal-body{ padding:30px; background:var(--surface-0); }

/* شبكة معلومات ثانوية (إن استُخدمت داخل المودال) */
.secondary-info-grid{
  display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
  gap:15px; margin-bottom:25px;
}
.info-item{
  display:flex; align-items:center; gap:10px; padding:8px 12px; border-radius:8px;
  background:var(--surface-0); box-shadow:var(--shadow-sm); border:1px solid var(--border-color);
}
.info-item i{ color:var(--btn-primary-bg, var(--primary-500)); font-size:1.1rem; width:24px; text-align:center; }
.info-item .label{ color:var(--text-muted); font-size:.9rem; margin-left:5px; }
.info-item .value{ font-weight:600; color:var(--text-strong); }

/* وصف المهمة */
.description-section{
  background:linear-gradient(to left, var(--surface-1), var(--info-100));
  border-radius:12px; padding:10px; margin-bottom:30px; text-align:right;
  box-shadow:var(--shadow-sm); position:relative; overflow:hidden;
  border:1px solid var(--primary-500);
}
.description-section::before{
  content:""; position:absolute; top:0; right:0; width:100%; height:5px;
  background:linear-gradient(to right, var(--accent-500), var(--accent-600));
}
.description-section h3{
  color:var(--accent-600); margin-bottom:20px; font-size:1.3rem;
  display:flex; align-items:center; gap:15px;
}
.description-section p{
  font-size:1.05rem; line-height:1.8; color:var(--text-strong);
  margin-top:15px; padding:15px; background:var(--surface-0);
  border-radius:10px; border:1px solid var(--border-color);
  white-space:pre-line; /* دعم فواصل الأسطر */
}

/* ملفات داخل المودال */
.view-modal .files-section{ margin-bottom:30px; }
.view-modal .section-title{
  font-size:1.3rem; color:var(--text-strong); margin-bottom:20px;
  padding-right:15px; border-right:5px solid var(--btn-primary-bg, var(--primary-500));
  display:inline-flex; align-items:center; gap:12px;
}
.view-modal .files-container{
  display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));
  gap:20px;
}
.view-modal .file-card{
  border-radius:12px; padding:20px; text-align:right; box-shadow:var(--shadow-sm);
  border:1px solid var(--primary-500); transition:var(--transition);
  display:flex; flex-direction:column; position:relative; overflow:hidden;
}
.view-modal .file-card:hover{ transform:translateY(-5px); box-shadow:var(--shadow-md); }
.view-modal .file-card::before{
  content:""; position:absolute; top:0; right:0; width:100%; height:4px;
  background:var(--btn-primary-bg, var(--primary-500));
}
.view-modal .file-header{ display:flex; align-items:center; gap:15px; margin-bottom:15px; }
.view-modal .file-icon{
  width:50px; height:50px; border-radius:10px; background:var(--surface-1);
  display:flex; align-items:center; justify-content:center; font-size:1.5rem;
  color:var(--btn-primary-bg, var(--primary-500)); flex-shrink:0; border:1px solid var(--border-color);
}
.view-modal .file-info{ flex-grow:1; text-align:right; }
.view-modal .file-name{
  font-weight:700; color:var(--text-strong); margin-bottom:5px;
  display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
  overflow:hidden; text-overflow:ellipsis;
}
.view-modal .file-actions{
  display:flex; justify-content:flex-end; gap:12px; margin-top:15px;
}
.view-modal .file-action-btn{
  padding:8px 16px; border-radius:6px; border:none; cursor:pointer; font-weight:600;
  font-size:.9rem; transition:var(--transition); display:inline-flex; align-items:center; gap:8px; text-decoration:none;
}
.view-modal .preview-btn{
  background:var(--surface-1); color:var(--text-strong); border:1px solid var(--border-color);
}
.view-modal .preview-btn:hover{ background:var(--surface-2); }
.view-modal .file-action-btn.btn-secondary.locked{ cursor:not-allowed; opacity:.6; }

/* Footer */
.view-modal .modal-footer{
  padding-top:20px; display:flex; justify-content:space-between;
}
.view-modal .action-btn{
  padding:12px 25px; border-radius:8px; border:none; cursor:pointer; font-weight:600;
  font-size:.9rem; transition:var(--transition); display:flex; align-items:center; gap:10px;
}
.view-modal .close-btn-task{
  background:var(--btn-primary-bg, var(--primary-500)); color:var(--text-inverse); max-width:250px;
}
.view-modal .close-btn-task:hover{
  background:var(--btn-primary-hover, var(--primary-600)); box-shadow:var(--shadow-sm);
}

/* استجابات */
@media (max-width:768px){
  .secondary-info-grid{ grid-template-columns:1fr; }
  .view-modal .files-container{ grid-template-columns:1fr; }
  .view-modal .modal-header h2{ font-size:1.4rem; }
  .description-section h3{ font-size:1.2rem; }
  .secondary-info-grid-header{ display:flex; flex-direction:column; }
}
@media (max-width:480px){
  .view-modal .modal-body{ padding:20px; }
  .view-modal .file-header{ text-align:center; }
  .view-modal .file-actions{ justify-content:center; }
}
@media (max-width:900px){
  .view-modal .modal-footer{ flex-direction:column; gap:10px; }
}
/* شبكة الملفات على شاشة صغيرة: عمود واحد */
@media (max-width: 560px){
  .view-modal{
    width: 100%;
    max-width: none;
    border-radius: 14px; /* زوايا أنعم على الجوال */
    max-height: min(100svh, 100dvh, 100vh);
  }
  .view-modal .modal-body{ padding: 16px; }
  .secondary-info-grid-header{ gap: 8px; }
  .view-modal .files-container{ grid-template-columns: 1fr; }
  .view-modal .section-title{ font-size: 1.1rem; }
  .view-modal .modal-header h2{ font-size: 1.25rem; gap: 10px; }
  .info-item-header i{ width: 20px; font-size: 1rem; }
}

/* لو الشاشة أضيق جداً (هواتف صغيرة جداً) نستخدم نمط “تقريباً ملء الشاشة” */
@media (max-width: 380px){
  .view-modal{
    border-radius: 10px;
  }
}
</style>
