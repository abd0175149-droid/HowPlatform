<?php
// app/Views/admin/pages/email_broadcast.php
$csrfTokenName  = function_exists('csrf_token') ? csrf_token() : 'csrf_token';
$csrfTokenValue = function_exists('csrf_hash')  ? csrf_hash()  : '';
?>
<style>
/* ===== الحاوية العامة ===== */
.eb-container{max-width:1400px;margin:0 auto;background:#fff;border-radius:16px;box-shadow:0 5px 25px rgba(0,0,0,.08);overflow:hidden}
.eb-header{background:linear-gradient(135deg,var(--primary-darker),var(--primary-dark));color:#fff;padding:25px 30px;display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap}
.eb-header h1{font-size:1.8rem;display:flex;align-items:center;gap:12px}
.eb-header .header-actions{display:flex;gap:10px;align-items:center}
.eb-hint{color:#eaf6ff;font-size:.95rem;display:flex;gap:8px;align-items:center}

/* ===== أدوات المساعدة ===== */
.eb-tools{display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:20px 30px;border-bottom:1px solid #eee;background:#f8fafc}
.tokens-panel,.templates-panel{background:#fff;border:1px solid var(--border-color,#e5e7eb);border-radius:12px;padding:14px;box-shadow:var(--shadow-sm)}
.eb-label{display:flex;align-items:center;gap:8px;font-weight:700;color:var(--primary-darker);margin-bottom:10px}
.eb-tokens{display:flex;flex-wrap:wrap;gap:8px}
.eb-chip{display:inline-flex;align-items:center;gap:6px;padding:8px 10px;font-size:.92rem;border-radius:999px;background:var(--primary-light);color:var(--primary-darker);cursor:pointer;user-select:none;transition:var(--transition)}
.eb-chip:hover{background:var(--primary);color:#fff}
.tpl-row{display:flex;gap:8px;flex-wrap:wrap}
.tpl-row .form-control{min-width:220px}

/* ===== منطقة الكتابة ===== */
.eb-write{display:grid;grid-template-columns:2fr 1fr;gap:16px;padding:20px 30px;border-bottom:1px solid #eee;background:#fff}
.form-control,.filter-select{padding:12px 15px;border-radius:8px;border:1px solid #ddd;background:#fff;font-size:1rem;transition:var(--transition)}
.form-control:focus,.filter-select:focus{border-color:var(--primary);outline:none;box-shadow:0 0 0 3px rgba(33,158,188,.2)}
.links-panel{background:#fff;border:1px solid var(--border-color,#e5e7eb);border-radius:12px;padding:14px;box-shadow:var(--shadow-sm);height:100%;max-width:550px}
.link-row{display:flex;gap:8px;margin-bottom:8px}
.eb-hint.sm{font-size:.85rem;color:var(--text-light)}
.eb-compose-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:0 30px 20px}

/* ===== شريط المستهدفين ===== */
.eb-list-header{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:16px 30px;background:#fff;border-bottom:1px solid #eee}
.eb-badge{display:inline-flex;align-items:center;gap:6px;background:var(--primary-light);color:var(--primary-darker);padding:6px 12px;border-radius:999px;font-weight:700;font-size:.95rem;border:1px solid rgba(0,0,0,.05)}

/* ===== المودال ===== */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);display:none;align-items:center;justify-content:center;z-index:1002}
.modal-overlay.active{display:flex}
.modal-card{width:min(1200px,96%);max-height:90vh;overflow:auto;background:#fff;border-radius:16px;box-shadow:0 16px 40px rgba(0,0,0,.2)}
.modal-head{display:flex;justify-content:space-between;align-items:center;padding:18px 22px;background:linear-gradient(135deg,var(--primary-darker),var(--primary-dark));color:#fff}
.modal-body{padding:18px 22px}
.modal-actions{display:flex;gap:8px;align-items:center;margin-bottom:12px}
.btn{padding:12px 20px;border:none;border-radius:8px;cursor:pointer;display:inline-flex;gap:8px;align-items:center;font-weight:600;transition:var(--transition)}
.btn-primary{background:var(--accent);color:var(--primary-darker)}
.btn-primary:hover{background:var(--accent-dark);transform:translateY(-2px)}
.btn-secondary{background:var(--primary);color:#fff}
.btn-secondary:hover{background:var(--primary-dark);transform:translateY(-2px)}
.btn-light{background:var(--btn-neutral-bg)}
.btn-light:hover{background:var(--btn-neutral-hover)}
.btn-xs{padding:6px 10px;font-size:.8rem}

/* فلاتر داخل المودال */
.filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin-bottom:12px}
.filter-group{display:flex;flex-direction:column;gap:6px}
.filter-group label{font-weight:600;color:var(--primary-darker);display:flex;gap:8px;align-items:center}
.filter-select[multiple]{min-height:160px}

/* جدول داخل المودال */
.courses-table{width:100%;border-collapse:collapse;min-width:980px}
.courses-table th{background:var(--primary);color:#fff;padding:8px 5px;text-align:center}
.courses-table td{padding:12px;border-bottom:1px solid #eee;text-align:center}
.courses-table tr:nth-child(even){background:#f8fafc}
.courses-table tr:hover{background:#e3f4ff}
.no-results{text-align:center;padding:30px;color:var(--text-light)}
.no-results i{font-size:4rem;color:var(--primary-light);margin-bottom:10px}

/* تقدّم الإرسال */
#sendProgress{margin:12px 30px 22px;background:#fff;border:1px dashed #ddd;border-radius:12px;padding:12px;display:none}
#sendStatus{color:var(--text-light);margin-bottom:6px}
#sendBar{height:8px;width:0;background:var(--accent);border-radius:999px;transition:width .3s}

/* المعاينة المضمّنة */
.preview-wrap{padding:12px 30px 24px;background:#fff}
.preview-head{display:flex;gap:8px;align-items:center;margin-bottom:8px}
.preview-pane{border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
.preview-pane iframe{width:100%;height:520px;border:0;display:block}

/* Pagination */
.pagination{display:flex;justify-content:center;align-items:center;margin-top:30px;gap:8px}
.pagination-btn{width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;background-color:white;color:var(--primary);border:1px solid #ddd;cursor:pointer;transition:var(--transition);font-weight:600}
.pagination-btn:hover:not(.disabled){background-color:var(--primary);color:white;border-color:var(--primary)}
.pagination-btn.active{background-color:var(--primary);color:white;border-color:var(--primary)}
.pagination-btn.disabled{opacity:0.5;cursor:not-allowed}
.pagination-info{margin:0 15px;color:var(--text-light);font-weight:500}

/* ===== Outbox Modal ===== */
.eb-ox-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;z-index:1100}
.eb-ox-overlay.active{display:block}
.eb-ox-modal{width:1000px;max-width:95vw;height:85vh;max-height:90vh;margin:40px auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:var(--shadow-lg);display:flex;flex-direction:column}
.eb-ox-header{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 16px;background:linear-gradient(135deg,var(--primary-darker),var(--primary-dark));color:#fff}
.eb-ox-actions{display:flex;align-items:center;gap:8px}
.eb-ox-body{flex:1;display:flex;min-height:0}
.eb-ox-sidebar{width:320px;min-width:260px;border-left:1px solid #eee;background:#fafafa;overflow-y:auto}
.eb-ox-list{list-style:none;margin:0;padding:0}
.eb-ox-content{flex:1;overflow-y:auto;padding:16px}
.eb-ox-empty{text-align:center;color:var(--text-light);padding:40px 10px}
.eb-ox-empty i{font-size:56px;color:var(--primary-light);display:block;margin-bottom:8px}

/* Files upload styles */
#eb-dropzone{border:2px dashed #cbd5e1;border-radius:10px;padding:14px;text-align:center;background:#f8fafc;cursor:pointer;transition:all 0.3s}
#eb-dropzone:hover{border-color:#94a3b8;background:#f1f5f9}
#eb-upload-progress{background:#e5e7eb;height:8px;border-radius:6px;overflow:hidden}
#eb-upload-bar{height:100%;background:#22c55e;transition:width 0.3s}
.chip{background:#eef2ff;color:#1e40af;padding:6px 10px;border-radius:999px;display:inline-flex;gap:8px;align-items:center;margin:2px}

/* Responsive */
@media (max-width:900px){
  .eb-tools{grid-template-columns:1fr}
  .eb-write{grid-template-columns:1fr}
}

/* Email body fixed dimensions */
#mail-body{width:800px;height:400px;max-width:100%;box-sizing:border-box}
</style>

<div id="emailBroadcastPage" class="eb-container" dir="rtl">
  <!-- الترويسة -->
  <div class="eb-header">
    <h1><i class="fas fa-envelope-open-text"></i> إرسال إشعارات بريدية</h1>
    <div class="header-actions">
      <span class="eb-hint"><i class="fas fa-info-circle"></i> استخدم «إرسال تجربة» لمراجعة الرسالة قبل الإرسال.</span>
      <button id="btn-open-targets" class="btn btn-secondary">
        <i class="fas fa-bullseye"></i> اختيار مستهدفون
      </button>
    </div>
  </div>

  <!-- أدوات المساعدة -->
  <section class="eb-tools">
    <!-- المتغيّرات المدعومة -->
    <div class="tokens-panel">
      <div class="eb-label"><i class="fas fa-tag"></i> المتغيّرات المدعومة (انقر للإدراج):</div>
      <div id="tokensList" class="eb-tokens">
        <span class="eb-chip">{title}</span>
        <span class="eb-chip">{name}</span>
        <span class="eb-chip">{email}</span>
        <span class="eb-chip">{phone}</span>
        <span class="eb-chip">{university}</span>
        <span class="eb-chip">{specialty}</span>
        <span class="eb-chip">{login_link}</span>
      </div>
    </div>

    <!-- القوالب المحفوظة -->
    <div class="templates-panel">
      <div class="eb-label"><i class="fas fa-folder"></i> القوالب المحفوظة</div>
      <div class="tpl-row" style="margin-bottom:8px">
        <input id="template-name" class="form-control" type="text" placeholder="اسم القالب (مثال: تذكير إكمال التسجيل)">
        <button id="btn-save-tpl" class="btn btn-light"><i class="fas fa-save"></i> حفظ كقالب</button>
      </div>
      <div class="tpl-row">
        <select id="template-select" class="form-control">
          <option value="">— اختيار قالب محفوظ —</option>
        </select>
        <button id="btn-apply-tpl" class="btn btn-light"><i class="fas fa-download"></i> تطبيق</button>
        <button id="btn-delete-tpl" class="btn btn-light"><i class="fas fa-trash"></i> حذف</button>
        <button id="btn-refresh-tpl" class="btn btn-light"><i class="fas fa-sync"></i> تحديث القوالب</button>
      </div>
    </div>
  </section>

  <!-- عنوان الرسالة -->
  <section style="padding:16px 30px;background:#fff;border-bottom:1px solid #eee">
    <input type="text" id="mail-subject" class="form-control" placeholder="الموضوع" style="margin-bottom:10px">
    <input type="text" id="mail-preheader" class="form-control" placeholder="سطر المعاينة (اختياري: يظهر بجانب العنوان في صناديق البريد)" style="margin-bottom:6px">
    <small class="small">يفضّل 35–90 حرفًا. يُستخدم كسطر المعاينة في تطبيقات البريد.</small>
  </section>

  <!-- منطقة الكتابة -->
  <section class="eb-write">
    <!-- نص الرسالة -->
    <div>
      <textarea id="mail-body" class="form-control" rows="12" placeholder="اكتب نص الرسالة هنا. مثال:
{title} {name}،
نودّ تذكيرك باستكمال ملفك الشخصي للاستفادة القصوى من منصّة كيف.
الجامعة: {university} | التخصص: {specialty}
رابط الدخول: {login_link}"></textarea>
    </div>

    <!-- إدراج رابط + الملفات -->
    <div class="links-panel">
      <label class="eb-label"><i class="fas fa-link"></i> إدراج رابط داخل النص:</label>
      <div class="link-row">
        <input id="link-url" type="url" class="form-control" placeholder="https://example.com">
      </div>
      <div class="link-row">
        <input id="link-label" type="text" class="form-control" placeholder="نص الرابط (اختياري)">
      </div>
      <button id="btn-insert-link" type="button" class="btn btn-secondary" style="width:100%">
        <i class="fas fa-paperclip"></i> إدراج الرابط عند المؤشر
      </button>
      <div class="eb-hint sm" style="margin-top:10px">سيُدرج كعنصر HTML آمن داخل نص الرسالة.</div>

      <!-- منطقة الصور/المرفقات -->
      <hr style="margin:16px 0">
      <label class="eb-label" style="display:block;margin-bottom:8px">
        <i class="fas fa-images"></i> الصور و المرفقات:
      </label>

      <!-- اختيار النوع -->
      <div class="link-row" style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
        <div style="flex:1">
          <select id="eb-file-type" class="form-control">
            <option value="image">صورة (&lt;img&gt; داخل النص)</option>
            <option value="attachment">مرفق (يُرسل كمرفق)</option>
          </select>
        </div>
        <button id="btn-refresh-files" type="button" class="btn btn-light" title="تحديث قائمة ملفاتي">
          <i class="fas fa-sync"></i>
        </button>
      </div>

      <!-- منطقة السحب والإفلات -->
      <div id="eb-dropzone" tabindex="0" aria-label="اسحب الملف هنا أو انقر لاختيار ملف">
        <div style="pointer-events:none">
          <i class="fas fa-cloud-upload-alt" style="font-size:22px"></i>
          <div style="margin-top:6px">اسحب الملف هنا أو <u>انقر للاختيار</u></div>
          <div class="sm" style="color:#64748b;margin-top:4px">الحد الأقصى ~ 12MB</div>
        </div>
        <input id="eb-file-input" type="file" accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx,.zip,.txt" style="display:none">
      </div>

      <!-- شريط التقدم -->
      <div id="eb-upload-progress" style="display:none;margin-top:10px">
        <div id="eb-upload-bar"></div>
      </div>

      <!-- أزرار الرفع -->
      <div class="link-row" style="display:flex;gap:8px;margin-top:10px">
        <button id="eb-btn-upload" type="button" class="btn btn-primary" style="flex:1" disabled>
          <i class="fas fa-upload"></i> رفع الملف
        </button>
        <button id="eb-btn-clear-file" type="button" class="btn btn-light" style="flex:0 0 auto">
          مسح الاختيار
        </button>
      </div>

      <!-- قائمة ملفاتي المرفوعة -->
      <div style="margin-top:14px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
          <strong><i class="far fa-folder-open"></i> ملفاتي الأخيرة</strong>
          <span class="sm" style="color:#64748b">(اضغط لإدراج صورة أو إضافتها كمرفق)</span>
        </div>
        <ul id="eb-files-list" style="list-style:none;margin:0;padding:0;max-height:220px;overflow:auto;border:1px solid #eee;border-radius:8px">
          <li id="eb-files-empty" style="padding:10px;color:#64748b;text-align:center">لا توجد ملفات بعد.</li>
        </ul>
      </div>

      <!-- المرفقات المختارة للإرسال -->
      <div style="margin-top:12px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
          <strong><i class="fas fa-paperclip"></i> المرفقات المختارة للإرسال</strong>
          <span class="sm" style="color:#64748b">(ستُرسل كمرفقات بالفعل)</span>
        </div>
        <div id="eb-attachments-chips" style="display:flex;flex-wrap:wrap;gap:6px">
          <span id="eb-no-attachments" class="sm" style="color:#94a3b8">لا يوجد مرفقات محددة.</span>
        </div>
      </div>
    </div>
  </section>

  <!-- شريط المستهدفين -->
  <div class="eb-list-header">
    <div style="display:flex;align-items:center;gap:8px">
      <h3 style="margin:0;color:var(--primary-darker)"><i class="fas fa-users"></i> المستهدفون</h3>
      <span class="eb-badge"><i class="fas fa-database"></i> الإجمالي: <span id="totalCount">0</span></span>
      <span class="eb-badge"><i class="fas fa-check-circle"></i> المحدَّد: <span id="selectedCount">0</span></span>
    </div>
    <div style="display:flex;gap:8px">
      <button id="btn-open-targets" class="btn btn-secondary"><i class="fas fa-bullseye"></i> اختيار مستهدفون</button>
      <button id="btn-open-outbox" class="btn btn-light"><i class="fas fa-inbox"></i> البريد الصادر</button>
      <button id="btn-send" class="btn btn-primary"><i class="fas fa-paper-plane"></i> إرسال الآن</button>
    </div>
  </div>

  <!-- أزرار الإرسال والتجربة -->
  <div class="eb-compose-row">
    <input type="email" id="test-email" class="form-control" placeholder="بريد التجربة" style="max-width:250px">
    <label style="display:flex;align-items:center;gap:6px">
      <input type="checkbox" id="dry-run"> وضع المعاينة فقط (بدون إرسال فعلي)
    </label>
    <button id="btn-send-test" class="btn btn-secondary"><i class="fas fa-flask"></i> إرسال تجربة</button>
  </div>

  <!-- شريط التقدم -->
  <div id="sendProgress">
    <div id="sendStatus"></div>
    <div style="height:8px;background:#eee;border-radius:999px;overflow:hidden">
      <div id="sendBar"></div>
    </div>
  </div>

  <!-- منطقة المعاينة المضمّنة -->
  <section id="inlinePreviewSection" class="preview-wrap">
    <div class="preview-head">
      <button id="btn-preview" class="btn btn-light"><i class="fas fa-eye"></i> معاينة الرسالة (لأول مستهدف محدد)</button>
      <button id="btn-preview-clear" class="btn btn-light"><i class="fas fa-times"></i> إخفاء المعاينة</button>
    </div>
    <div id="previewPane" class="preview-pane" style="display:none">
      <iframe id="previewFrame" title="Email Preview"></iframe>
    </div>
  </section>

  <!-- مودال اختيار المستهدفين -->
  <div id="targetsOverlay" class="modal-overlay">
    <div id="targetsModal" class="modal-card" role="dialog" aria-modal="true">
      <div class="modal-head">
        <h3 style="margin:0;display:flex;gap:8px;align-items:center">
          <i class="fas fa-filter"></i> اختيار المستهدفين
        </h3>
        <button id="btn-close-targets" class="btn btn-light"><i class="fas fa-times"></i> إغلاق</button>
      </div>

      <div class="modal-body">
        <!-- الفلاتر -->
        <div class="filter-grid" dir="rtl">
          <div class="filter-group">
            <label><i class="fas fa-user-check"></i> اكتمال التسجيل</label>
            <select id="f-is-complete" class="filter-select">
              <option value="">الكل</option>
              <option value="1">مكتمل</option>
              <option value="0">غير مكتمل</option>
            </select>
            <small id="completeCounts" class="small"></small>
          </div>

          <div class="filter-group">
            <label><i class="fas fa-venus-mars"></i> الجنس</label>
            <select id="f-gender" class="filter-select">
              <option value="">الكل</option>
              <option value="male">ذكور</option>
              <option value="female">إناث</option>
            </select>
          </div>

          <div class="filter-group">
            <label><i class="fas fa-user-clock"></i> لم يسجّل الدخول منذ</label>
            <input id="f-inactive-since" type="date" class="filter-select">
            <small class="small">يشمل من لم يسجّل الدخول مطلقًا</small>
          </div>

          <div class="filter-group">
            <label><i class="fas fa-id-card"></i> نوع الحساب</label>
            <select id="f-account-type" class="filter-select" multiple size="6"></select>
          </div>

          <div class="filter-group">
            <label><i class="far fa-paper-plane"></i> أُرسل له؟</label>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
              <select id="f-emailed-before" class="filter-select" style="min-width:170px">
                <option value="">الكل</option>
                <option value="1">تم الإرسال له</option>
                <option value="0">لم يتم الإرسال له</option>
              </select>
              <div style="display:flex;gap:8px;align-items:center">
                <div>
                  <label for="f-emailed-from" class="form-label small" style="display:block;margin:0 0 4px">من</label>
                  <input id="f-emailed-from" type="date" class="filter-select" />
                </div>
                <div>
                  <label for="f-emailed-to" class="form-label small" style="display:block;margin:0 0 4px">إلى</label>
                  <input id="f-emailed-to" type="date" class="filter-select" />
                </div>
              </div>
            </div>
            <small class="small">الفترة اختيارية؛ إن تُركت فارغة يُطبّق الشرط على كل الوقت.</small>
          </div>

          <div class="filter-group">
            <label><i class="fas fa-university"></i> الجامعة</label>
            <select id="f-university" class="filter-select" multiple size="6"></select>
          </div>

          <div class="filter-group">
            <label><i class="fas fa-graduation-cap"></i> التخصص</label>
            <select id="f-specialty" class="filter-select" multiple size="6"></select>
          </div>
        </div>

        <div class="modal-actions">
          <button id="btn-reset" class="btn btn-light"><i class="fas fa-undo-alt"></i> إعادة ضبط</button>
          <button id="btn-select-all" class="btn btn-light"><i class="fas fa-check-double"></i> تحديد الجميع</button>
          <button id="btn-clear-all" class="btn btn-light"><i class="fas fa-times-circle"></i> إلغاء التحديد</button>
        </div>

        <div style="overflow:auto">
          <table id="recipientsTable" class="courses-table">
            <thead>
              <tr>
                <th><input type="checkbox" id="chk-all"></th>
                <th>الاسم</th>
                <th>البريد</th>
                <th>الهاتف</th>
                <th>الجامعة</th>
                <th>التخصص</th>
                <th>الجنس</th>
                <th>مكتمل؟</th>
                <th>آخر دخول</th>
                <th>معاينة</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

        <div id="noResults" class="no-results" style="display:none">
          <i class="far fa-folder-open"></i>
          <h3>لا توجد نتائج مطابقة</h3>
          <p>جرّب تغيير معايير الفلترة، أو إزالة بعضها.</p>
        </div>

        <!-- الباجينيشن -->
        <div id="pagination" class="pagination" dir="rtl">
          <button class="pagination-btn" id="firstPageBtn" title="الأولى"><i class="fas fa-angle-double-right"></i></button>
          <button class="pagination-btn" id="prevPageBtn" title="السابق"><i class="fas fa-angle-right"></i></button>
          <div class="pagination-info" id="paginationInfo">صفحة 1 من 1</div>
          <button class="pagination-btn" id="nextPageBtn" title="التالي"><i class="fas fa-angle-left"></i></button>
          <button class="pagination-btn" id="lastPageBtn" title="الأخيرة"><i class="fas fa-angle-double-left"></i></button>
        </div>
      </div>
    </div>
  </div>

  <!-- مودال البريد الصادر -->
  <div id="outboxModal" class="eb-ox-overlay" style="display:none">
    <div class="eb-ox-modal" role="dialog" aria-modal="true" aria-labelledby="oxTitle">
      <div class="eb-ox-header">
        <h3 id="oxTitle"><i class="fas fa-paper-plane"></i> البريد الصادر</h3>
        <div class="eb-ox-actions">
          <input id="outboxSearch" class="form-control" type="search" placeholder="ابحث بالبريد الإلكتروني..." style="width:200px">
          <button id="outboxClose" class="btn btn-light"><i class="fas fa-times"></i></button>
        </div>
      </div>
      <div class="eb-ox-body">
        <aside class="eb-ox-sidebar">
          <ul id="outboxList" class="eb-ox-list"></ul>
        </aside>
        <main class="eb-ox-content">
          <div id="outboxDetails" class="eb-ox-details">
            <div class="eb-ox-empty">
              <i class="far fa-envelope-open"></i>
              <p>اختر رسالة لعرض تفاصيلها</p>
            </div>
          </div>
        </main>
      </div>
    </div>
  </div>

  <!-- CSRF Meta Tags -->
  <meta name="csrf-token-name" content="<?= esc($csrfTokenName) ?>">
  <meta name="csrf-token-value" content="<?= esc($csrfTokenValue) ?>">
</div>