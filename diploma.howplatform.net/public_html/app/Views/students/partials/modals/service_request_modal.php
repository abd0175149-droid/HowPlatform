<!-- مودال طلب خدمة متخصصة (إعادة تصميم — NotebookLM flat) -->
<div id="serviceRequestModal" class="service-modal" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="service-modal-content" role="document">
    <div class="srm-header">
      <div class="srm-title">
        <span class="srm-title-ic"><i class="fas fa-headset"></i></span>
        <div>
          <h2>طلب خدمة متخصصة</h2>
          <p>اشرح طلبك وسيردّ «فَطين» مبدئياً ثم يتابعك الفريق الأكاديمي.</p>
        </div>
      </div>
      <button type="button" class="srm-close" id="srvReqCloseBtn" aria-label="إغلاق"><i class="fas fa-times"></i></button>
    </div>

    <form id="serviceRequestForm" class="srm-body" novalidate>
      <div class="srm-field">
        <label for="requestSubject">موضوع الطلب <span class="srm-req">*</span></label>
        <input type="text" id="requestSubject" name="subject" maxlength="255" placeholder="مثال: مساعدة في كتابة مقال تحليلي نقدي" autocomplete="off">
        <div class="invalid-feedback" data-for="requestSubject"></div>
      </div>

      <div class="srm-field">
        <label for="requestCourse">المادة المرتبطة <span class="srm-opt">(اختياري)</span></label>
        <select id="requestCourse" name="course_offering_id">
          <option value="">— خدمة عامة —</option>
        </select>
      </div>

      <div class="srm-field">
        <label for="requestDescription">تفاصيل الطلب <span class="srm-req">*</span></label>
        <textarea id="requestDescription" name="description" rows="4" placeholder="اكتب تفاصيل ما تحتاجه بوضوح…"></textarea>
        <div class="invalid-feedback" data-for="requestDescription"></div>
      </div>

      <div class="srm-field">
        <label>المرفقات <span class="srm-opt">(اختياري)</span></label>
        <div class="srm-drop" id="fileUploadArea">
          <input type="file" id="requestFiles" multiple hidden>
          <button type="button" class="srm-drop-btn" id="pickFilesBtn">
            <i class="fas fa-cloud-upload-alt"></i>
            <span>اسحب الملفات هنا أو انقر للاختيار</span>
          </button>
          <div class="srm-files" id="uploadedFilesList"></div>
        </div>
      </div>
    </form>

    <div class="srm-footer">
      <button type="button" class="srm-btn srm-btn-ghost" id="srvReqCancelBtn">إلغاء</button>
      <button type="submit" form="serviceRequestForm" class="srm-btn srm-btn-primary" id="srvReqSubmitBtn">
        <i class="fas fa-paper-plane"></i> إرسال الطلب
      </button>
    </div>

    <!-- طبقة لودر/نجاح أثناء الإرسال -->
    <div class="srv-overlay" id="srvReqOverlay" aria-hidden="true">
      <div class="srv-overlay-box">
        <div class="srv-spinner" id="srvSpin"></div>
        <div class="srv-check" id="srvCheck" style="display:none;"><i class="fas fa-check"></i></div>
        <h3 id="srvOverlayTitle">جارٍ معالجة طلبك…</h3>
        <p id="srvOverlayText">يرجى الانتظار قليلاً — نُرسل طلبك ونجهّز رد «فَطين».</p>
      </div>
    </div>
  </div>
</div>

<style>
/* زر فتح المودال (يُستخدم في لوحة التحكم) — مسطّح */
.btn-service-request { background: var(--accent-500); color: #fff; border: none; padding: 8.8px 16px;
  border-radius: 799.2px; font-size: .92rem; font-weight: 700; cursor: pointer; display: inline-flex;
  align-items: center; gap: 6.4px; transition: background .16s; }
.btn-service-request:hover { background: var(--accent-600); }

/* ===== الغلاف ===== */
.service-modal { display: none; position: fixed; inset: 0; z-index: 10000; align-items: center; justify-content: center;
  padding: 14.4px; background: rgba(10, 22, 34, .55); backdrop-filter: blur(3.2px); -webkit-backdrop-filter: blur(3.2px); }
.service-modal.show { display: flex; animation: srmFade .2s ease; }
@keyframes srmFade { from { opacity: 0 } to { opacity: 1 } }

.service-modal-content { position: relative; width: 100%; max-width: 448px; max-height: 92vh; overflow-y: auto;
  background: var(--surface-0, #fff); border: 0.8px solid var(--border-color, #e7eaee); border-radius: var(--border-radius, 12.8px);
  box-shadow: var(--shadow-lg, 0 14.4px 38.4px rgba(0,0,0,.18)); animation: srmPop .22s ease; }
@keyframes srmPop { from { transform: translateY(11.2px) scale(.98); opacity: 0 } to { transform: none; opacity: 1 } }

/* ===== الترويسة ===== */
.srm-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 9.6px;
  padding: 16px 17.6px; border-bottom: 0.8px solid var(--border-color, #e7eaee); }
.srm-title { display: flex; gap: 10.4px; align-items: center; }
.srm-title-ic { width: 36.8px; height: 36.8px; border-radius: 10.4px; background: var(--primary-50, #e8f5fb); color: var(--primary-600, #1b81a0);
  display: grid; place-items: center; font-size: 1.2rem; flex-shrink: 0; }
.srm-title h2 { margin: 0; font-size: 1.18rem; font-weight: 800; color: var(--text-strong, #023047); }
.srm-title p { margin: 2.4px 0 0; font-size: .85rem; color: var(--text-muted, #64748b); line-height: 1.5; }
.srm-close { width: 30.4px; height: 30.4px; border-radius: 50%; border: 0.8px solid var(--border-color, #e7eaee); background: var(--surface-1, #f4f6f9);
  color: var(--text-muted, #64748b); cursor: pointer; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; transition: .15s; }
.srm-close:hover { background: var(--surface-2, #e6e6e6); color: var(--text-strong, #023047); }

/* ===== الجسم ===== */
.srm-body { padding: 16px 17.6px; display: flex; flex-direction: column; gap: 12.8px; }
.srm-field { display: flex; flex-direction: column; gap: 5.6px; }
.srm-field label { font-weight: 700; font-size: .9rem; color: var(--text-strong, #023047); }
.srm-req { color: var(--error-500, #e74c3c); }
.srm-opt { color: var(--text-muted, #64748b); font-weight: 400; font-size: .8rem; }
.srm-field input[type=text], .srm-field select, .srm-field textarea {
  width: 100%; padding: 8.8px 11.2px; border: 0.8px solid var(--border-color, #e7eaee); border-radius: 9.6px;
  background: var(--surface-1, #f4f6f9); color: var(--text-strong, #023047); font: inherit; font-size: .95rem; transition: .15s; }
.srm-field textarea { resize: vertical; min-height: 76.8px; line-height: 1.6; }
.srm-field input:focus, .srm-field select:focus, .srm-field textarea:focus {
  outline: none; border-color: var(--primary-400, #5fb6e2); box-shadow: var(--focus-ring, 0 0 0 2.4px rgba(33,158,188,.25)); background: var(--surface-0, #fff); }
.srm-field input.is-invalid, .srm-field textarea.is-invalid { border-color: var(--error-500, #e74c3c); }
.invalid-feedback { color: var(--error-600, #c0392b); font-size: .8rem; min-height: 0; }
.invalid-feedback:empty { display: none; }

/* ===== الإفلات/المرفقات ===== */
.srm-drop { border: 1.2px dashed var(--border-strong, #d8dde3); border-radius: 11.2px; padding: 6.4px; transition: .15s; background: var(--surface-1, #f4f6f9); }
.srm-drop.dragover { border-color: var(--primary-500, #219ebc); background: var(--primary-50, #e8f5fb); }
.srm-drop-btn { width: 100%; background: none; border: none; cursor: pointer; color: var(--text-muted, #64748b);
  display: flex; flex-direction: column; align-items: center; gap: 4.8px; padding: 14.4px; font: inherit; font-size: .88rem; }
.srm-drop-btn i { font-size: 1.6rem; color: var(--primary-500, #219ebc); }
.srm-files { display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: 8px; padding: 3.2px; }
.srm-files:empty { display: none; }
.srm-files .file-card { position: relative; background: var(--surface-0, #fff); border: 0.8px solid var(--border-color, #e7eaee);
  border-radius: 9.6px; padding: 6.4px; display: flex; flex-direction: column; align-items: center; gap: 4.8px; }
.srm-files .remove-file { position: absolute; top: 4px; inset-inline-end: 4px; width: 17.6px; height: 17.6px; border-radius: 50%;
  border: none; background: var(--surface-2, #e6e6e6); color: var(--text-muted, #64748b); cursor: pointer; font-size: .7rem; display: grid; place-items: center; }
.srm-files .remove-file:hover { background: var(--error-500, #e74c3c); color: #fff; }
.srm-files .thumb { width: 100%; height: 51.2px; border-radius: 6.4px; background: var(--surface-1, #f4f6f9); display: grid; place-items: center; overflow: hidden; color: var(--primary-500, #219ebc); font-size: 1.5rem; }
.srm-files .thumb img { width: 100%; height: 100%; object-fit: cover; }
.srm-files .meta { width: 100%; text-align: center; }
.srm-files .meta .name { font-size: .75rem; font-weight: 600; color: var(--text-strong, #023047); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.srm-files .meta .sub { font-size: .68rem; color: var(--text-muted, #64748b); }

/* ===== التذييل ===== */
.srm-footer { display: flex; justify-content: flex-end; gap: 8px; padding: 12.8px 17.6px; border-top: 0.8px solid var(--border-color, #e7eaee); }
.srm-btn { border: 0.8px solid transparent; border-radius: 799.2px; padding: 8px 16px; font: inherit; font-weight: 700; font-size: .92rem;
  cursor: pointer; display: inline-flex; align-items: center; gap: 5.6px; transition: .15s; }
.srm-btn-ghost { background: var(--surface-1, #f4f6f9); color: var(--text-strong, #023047); border-color: var(--border-color, #e7eaee); }
.srm-btn-ghost:hover { background: var(--surface-2, #e6e6e6); }
.srm-btn-primary { background: var(--primary-500, #219ebc); color: #fff; }
.srm-btn-primary:hover { background: var(--primary-600, #1b81a0); }
.srm-btn-primary:disabled { opacity: .6; cursor: not-allowed; }

/* ===== طبقة اللودر/النجاح ===== */
.srv-overlay { position: absolute; inset: 0; display: none; align-items: center; justify-content: center;
  background: rgba(255,255,255,.94); border-radius: inherit; z-index: 6; }
[data-theme="dark"] .srv-overlay { background: rgba(15,23,42,.95); }
.srv-overlay.show { display: flex; }
.srv-overlay-box { text-align: center; padding: 19.2px; max-width: 272px; }
.srv-overlay-box h3 { margin: 11.2px 0 4.8px; color: var(--text-strong, #0f172a); font-size: 1.15rem; }
.srv-overlay-box p { color: var(--text-muted, #64748b); font-size: .92rem; margin: 0; line-height: 1.6; }
.srv-spinner { width: 43.2px; height: 43.2px; border-radius: 50%; border: 3.2px solid rgba(33,158,188,.2); border-top-color: #219ebc; margin: 0 auto; animation: srvspin .9s linear infinite; }
@keyframes srvspin { to { transform: rotate(360deg); } }
.srv-check { width: 43.2px; height: 43.2px; border-radius: 50%; background: #2ecc71; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin: 0 auto; animation: srvpop .3s ease; }
@keyframes srvpop { from { transform: scale(.6); opacity: 0 } to { transform: scale(1); opacity: 1 } }

@media (max-width: 560px) {
  .srm-header, .srm-body, .srm-footer { padding-inline: 12.8px; }
  .srm-title p { display: none; }
}
</style>
