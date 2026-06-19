
<div id="serviceRequestModal" class="service-modal" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="service-modal-content task-modal" role="document">
    <div class="service-modal-header modal-header">
      <h2><i class="fas fa-hands-helping"></i> طلب خدمة متخصصة</h2>
      <button type="button" class="close-modal" id="srvReqCloseBtn" aria-label="إغلاق">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <form id="serviceRequestForm" class="service-form modal-body" novalidate>
      <div class="form-grid">
        <div class="form-group">
          <label for="requestSubject">
            <i class="fas fa-heading"></i> موضوع الطلب
            <span class="required">*</span>
          </label>
          <input
            type="text"
            id="requestSubject"
            name="subject"
            placeholder="اكتب موضوع طلبك بوضوح..."
            required
            autocomplete="off"
          />
          <div class="invalid-feedback" data-for="requestSubject"></div>
        </div>

        <div class="form-group">
          <label for="requestCourse">
            <i class="fas fa-book"></i> المادة المرتبطة
          </label>
          <select id="requestCourse" name="course_offering_id">
            <option value="">-- اختر المادة (اختياري) --</option>
          </select>
          <small class="text-muted">اختياري: اربط الطلب بمادة محددة.</small>
        </div>
      </div>

      <div class="form-group">
        <label for="requestDescription">
          <i class="fas fa-align-left"></i> تفاصيل الطلب
          <span class="required">*</span>
        </label>
        <textarea
          id="requestDescription"
          name="description"
          rows="5"
          placeholder="اشرح تفاصيل طلبك أو المشكلة التي تواجهها..."
          required
        ></textarea>
        <div class="invalid-feedback" data-for="requestDescription"></div>
      </div>

      <div class="form-group">
        <label>
          <i class="fas fa-paperclip"></i> المرفقات
          <span class="optional">(اختياري)</span>
        </label>

        <div class="file-upload-area file-upload-section" id="fileUploadArea">
          <input
            type="file"
            id="requestFiles"
            multiple
            accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar"
            style="display:none"
          />

          <button type="button" class="upload-placeholder file-upload-header" id="pickFilesBtn">
            <i class="fas fa-cloud-upload-alt"></i>
            <p class="upload-hint">اسحب الملفات هنا أو انقر للاختيار</p>
            <span class="file-hint">PDF, Word, Excel, PowerPoint, صور (حد أقصى 10MB لكل ملف)</span>
          </button>

          <div class="uploaded-files file-preview-container" id="uploadedFilesList"></div>
        </div>
      </div>

      <div class="form-actions modal-footer">
        <button type="button" class="btn btn-secondary btn-cancel" id="srvReqCancelBtn">
          <i class="fas fa-times"></i> إلغاء
        </button>
        <button type="submit" class="btn btn-primary btn-submit" id="srvReqSubmitBtn">
          <i class="fas fa-paper-plane"></i> إرسال الطلب
        </button>
      </div>
    </form>
  </div>
</div>

<style>
    /* ===== زر فتح المودال (مسموح يبقى لأنه مرتبط بالمودال) ===== */
.btn-service-request {
  background: linear-gradient(135deg, #fd9e02, #fb8500);
  color: #fff;
  border: none;
  padding: 12px 24px;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: .3s ease;
  box-shadow: 0 4px 15px rgba(253, 158, 2, .3);
}
.btn-service-request:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(253, 158, 2, .4);
}

/* ===== Overlay الخاص بالمودال ===== */
.service-modal {
  display: none;
  position: fixed;
  z-index: 10000;
  inset: 0;
  background-color: rgba(0,0,0,0.7);
  backdrop-filter: blur(4px);
  animation: fadeIn .25s ease;
  font-family: "Almarai", sans-serif;
}
.service-modal.show { display: flex; align-items: center; justify-content: center; }

/* ===== صندوق المودال (مطابق للتصميم المطلوب) ===== */
.service-modal-content.task-modal {
  background: var(--card-bg, #fff);
  border-radius: 20px;
  width: 90%;
  max-width: 900px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 8px 20px rgba(0,0,0,.1);
  transform: translateY(30px) scale(.96);
  opacity: 0;
  transition: transform .3s ease, opacity .3s ease;
}
.service-modal.show .service-modal-content.task-modal {
  transform: translateY(0) scale(1);
  opacity: 1;
}

/* ===== هيدر المودال ===== */
.service-modal-header.modal-header {
  background: linear-gradient(135deg, var(--primary-darker, #023047), var(--primary-dark, #0f6f87));
  color: #fff;
  padding: 22px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-top-left-radius: 20px;
  border-top-right-radius: 20px;
  position: relative;
}
.service-modal-header.modal-header::before{
  content:"";
  position:absolute; inset:0;
  background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><circle cx="25" cy="25" r="5" fill="rgba(255,255,255,0.08)"/><circle cx="70" cy="70" r="8" fill="rgba(255,255,255,0.08)"/><circle cx="95" cy="35" r="4" fill="rgba(255,255,255,0.08)"/></svg>');
  opacity:.5;
  pointer-events:none;
  border-top-left-radius: 20px;
  border-top-right-radius: 20px;
}
.service-modal-header h2{
  margin:0; font-size:1.6rem; display:flex; gap:10px; align-items:center;
}
.service-modal-header .close-modal{
  background: rgba(255,255,255,.18);
  border:none; color:#fff; width:42px; height:42px; border-radius:50%;
  display:flex; align-items:center; justify-content:center; cursor:pointer;
  transition: .3s ease;
}
.service-modal-header .close-modal:hover{ background: rgba(255,255,255,.28); transform: rotate(90deg); }

/* ===== جسم المودال ===== */
.service-form.modal-body{
  padding: 26px;
  background: var(--background, #f8fafc);
  max-height: calc(90vh - 150px);
  overflow-y: auto;
}
.service-form.modal-body::-webkit-scrollbar{ width:8px; }
.service-form.modal-body::-webkit-scrollbar-thumb{ background: var(--primary, #219ebc); border-radius:4px; }

/* ===== شبكة الحقول (للمظهر فقط) ===== */
.form-grid{
  display:grid; gap:20px;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  margin-bottom: 20px;
}

/* ===== الحقول واللّيبل ===== */
.form-group { margin-bottom: 20px; }
.form-group label{
  display:flex; gap:8px; align-items:center; margin-bottom:8px;
  font-weight:600; color: var(--primary-700); font-size:14px;
}
.form-group label i{ color: var(--primary, #219ebc); font-size:16px; }
.required{ color:#e74c3c; font-size:12px; }
.optional{ color:#95a5a6; font-size:12px; }
.form-group input,
.form-group select,
.form-group textarea{
  width:100%; padding: 12px 16px; border-radius: 12px;
  border: 2px solid var(--primary-300); background:var(--surface-0); font-size:14px;
  transition: .3s ease; font-family: inherit;color: var(--text);
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus{
  outline:none; border-color: var(--primary-500);
  box-shadow: 0 0 0 3px rgba(33,158,188,.1);
}

/* ===== منطقة رفع الملفات (مُراعيّة لكلاسات السكربت) ===== */
.file-upload-area{
  border: 2px dashed var(--primary-300);
  border-radius: 12px;
  padding: 20px;
  transition: .3s ease;
  background: var(--surface-0);
}
.file-upload-area.dragover{
  border-color: var(--primary, #219ebc);
  background: rgba(33,158,188, .05);
}
.upload-placeholder{ text-align:center; cursor:pointer; padding:20px; }
.upload-placeholder i{ font-size:48px; color: var(--primary, #219ebc); margin-bottom:10px; }
.upload-placeholder p{ margin:10px 0; color: var(--primary-darker, #023047); font-weight:500; }
.file-hint{ color:#95a5a6; font-size:12px; }

/* قائمة الملفات المُرفوعة (JS يعتمد هذه البنية) */
.uploaded-files{ margin-top:15px; }
.file-item{
  display:flex; align-items:center; justify-content:space-between;
  padding:10px 15px; background:#f8f9fa; border-radius:10px; margin-bottom:8px;
}
.file-item .file-info{ display:flex; align-items:center; gap:10px; }
.file-item .file-icon{ color: var(--primary, #219ebc); }
.file-item .file-name{ font-size:14px; color: var(--primary-darker, #023047); }
.file-item .file-size{ font-size:12px; color:#95a5a6; }
.file-item .remove-file{
  background:none; border:none; color:#e74c3c; cursor:pointer; padding:5px;
  transition: transform .2s ease;
}
.file-item .remove-file:hover{ transform: scale(1.2); }

/* ===== أزرار الإجراء داخل الفوتر ===== */
.form-actions{
  display:flex; gap:12px; justify-content:flex-end;
  margin-top:24px; padding-top:20px; border-top:1px solid #f0f0f0;
}
.btn-cancel, .btn-submit{
  padding: 12px 24px; border-radius: 12px; font-size: 14px; font-weight: 600;
  cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
  border: none; transition: .3s ease;
}
.btn-cancel{ background:#e1e8ed; color: var(--primary-darker, #023047); }
.btn-cancel:hover{ background:#d1d8e0; }
.btn-submit{
  background: linear-gradient(135deg, var(--accent, #fd9e02), var(--accent-dark, #fb8500));
  color:#fff; box-shadow: 0 4px 15px rgba(253,158,2, .3);
}
.btn-submit:hover{ transform: translateY(-2px); box-shadow: 0 6px 20px rgba(253,158,2,.4); }

/* ===== أنيميشن ===== */
@keyframes fadeIn { from {opacity:0} to {opacity:1} }

/* ===== Responsive ===== */
@media (max-width: 768px){
  .service-modal-content.task-modal{ width:95%; margin:10px; }
  .service-modal-header.modal-header{ padding: 18px; }
  .service-form.modal-body{ padding: 20px; }
}


/* ===== File Preview Cards — ثابت للشكل والحجم ===== */
.uploaded-files.file-preview-container {
  display: grid !important;
  gap: 14px !important;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)) !important;
  margin-top: 14px !important;
}

.file-card {
  position: relative;
  background: var(--surface-3);
  border: 1px solid var(--primary-500);
  border-radius: 12px;
  padding: 10px;
  box-shadow: 0 4px 10px rgba(0,0,0,.05);
  height: 190px;                /* ارتفاع ثابت للكارد */
  display: flex;
  flex-direction: column;
  align-items: stretch;
  overflow: hidden;
  align-items: center
}

/* مساحة المعاينة (صورة أو أيقونة) بنسبة ثابتة */
.file-card .thumb {
  width: 100%;
  aspect-ratio: 16 / 10;        /* يحافظ على نسبة أفقية مناسبة */
  background: var(--surface-2);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;             /* يمنع أي تجاوز */
}

/* الصور داخل الكارد: لا تتجاوز الحد */
.file-card .img-thumb img {
  width: 100%;
  height: 100%;
  object-fit: contain;          /* معاينة منطقية بدون قص */
}

/* أيقونة نوع الملف: كبيرة وواضحة */
.file-card .icon-thumb i {
  font-size: 64px;              /* تكبير الأيقونة */
  color: var(--primary, #219ebc);
  opacity: .9;
}

/* معلومات الملف */
.file-card .meta {
  margin-top: 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.file-card .meta .name {
  font-size: 13px;
  font-weight: 700;
  color: var(--primary-300);
  line-height: 1.2;
  display: -webkit-box;         /* سطران كحد أقصى مع حذف ذكي */
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
}
.file-card .meta .sub {
  font-size: 12px;
  color: #7d8790;
}

/* زر حذف الملف: عائم وأنيق */
.file-card .remove-file {
  position: absolute;
  top: 8px;
  inset-inline-end: 8px;        /* يدعم الاتجاه RTL */
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: var(--surface-0);
  border: 1px solid var(--error-100);
  color: var(--error-500);               /* أحمر أنيق */
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: transform .15s ease, background .2s ease;
  z-index: 2;
}
.file-card .remove-file:hover {
  transform: scale(1.06);
  background: var(--error-500);
  color: var(--text);
}

/* تحسينات صغيرة للشاشات الصغيرة */
@media (max-width: 480px) {
  .uploaded-files.file-preview-container {
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)) !important;
  }
  .file-card { height: 180px; }
  .file-card .icon-thumb i { font-size: 56px; }
}
/* ===== طبقات عامة (اختياري لتحكم مركزي) ===== */
:root{
  --z-modal: 10000;
  --z-toast: 20050;
  --z-tooltip: 20100;
  --z-dropdown: 20020;
}

/* مودال المنصة */
.service-modal{ z-index: var(--z-modal) !important; }

/* ===== تنبيهات / توست فوق المودال دائماً ===== */

/* SweetAlert2 */
.swal2-container{
  z-index: var(--z-toast) !important;
}

/* Toastr */
#toast-container{
  position: fixed !important;
  z-index: calc(var(--z-toast) + 10) !important;
}

/* iziToast */
.iziToast-wrapper, .iziToast{
  z-index: calc(var(--z-toast) + 20) !important;
}

/* Notyf */
.notyf{
  z-index: calc(var(--z-toast) + 30) !important;
}

/* Noty */
.noty_layout, .noty_bar{
  z-index: calc(var(--z-toast) + 40) !important;
}

/* لو عندك تنبيه مخصص */
.app-toast, .app-toast *{
  z-index: calc(var(--z-toast) + 50) !important;
  position: fixed; /* تأكد أنها خارج تدفّق المودال */
}

/* ===== عناصر أخرى يجب أن تطفو فوق المودال أيضاً (اختياري) ===== */
.select2-container{ z-index: calc(var(--z-dropdown)) !important; }
.flatpickr-calendar, .ui-datepicker{ z-index: calc(var(--z-dropdown) + 5) !important; }
.tippy-box, .tooltip, .popover{ z-index: var(--z-tooltip) !important; }
/* يجعل الفيديو داخل الكارد متّزنًا مثل الصور */
.file-card .video-thumb video{
  width: 100%;
  height: 100%;
  object-fit: contain;   /* أو cover إذا تحب ملء المساحة */
  border: 0;
  outline: none;
  border-radius: 10px;
}

</style> 