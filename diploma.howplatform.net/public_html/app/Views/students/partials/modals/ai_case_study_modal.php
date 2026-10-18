  <style>
    .ai-case-study-card {
      background: var(--surface, #fff);
      color: var(--text, #0a3247);
      border: 0.8px solid var(--border, #e1e5e9);
      border-radius: 11.2px;
      padding: 12.8px;
    }
    .ai-cs-header { display:flex; align-items:center; justify-content:space-between; gap:9.6px; margin-bottom:6.4px; }
    .ai-cs-title { margin:0; font-size:16px; }
    .ai-cs-actions { display:flex; gap:6.4px; align-items:center; }
    .ai-btn { padding:8px 11.2px; border-radius:8px; cursor:pointer; border:0.8px solid var(--primary,#219ebc); background:var(--primary,#219ebc); color:#fff; }
    .ai-btn.secondary { background:transparent; color:var(--primary,#219ebc); }
    .ai-hint { color: var(--text-light,#5a6c7d); font-size:11.2px; margin:4.8px 0 8px; }
    .ai-modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.35); display:none; align-items:center; justify-content:center; z-index:9999; }
    .ai-modal { width:min(720px,92vw); max-height:90vh; overflow:auto; background:#fff; border-radius:11.2px; border:0.8px solid #e1e5e9; box-shadow:0 9.6px 28.8px rgba(0,0,0,.18); }
    .ai-modal header, .ai-modal footer { padding:11.2px 12.8px; border-bottom:0.8px solid #e1e5e9; display:flex; align-items:center; justify-content:space-between; }
    .ai-modal footer { border-top:0.8px solid #e1e5e9; border-bottom:0; justify-content:flex-start; gap:6.4px; }
    .ai-modal .content { padding:11.2px 12.8px; }
    .ai-grid { display:grid; grid-template-columns:1fr 1fr; gap:9.6px; }
    .ai-grid .full { grid-column:1 / -1; }
    .ai-field { display:flex; flex-direction:column; gap:4.8px; }
    .ai-field input, .ai-field select, .ai-field textarea {
      border:0.8px solid #dcdfe4; border-radius:8px; padding:8px 9.6px; background:#fff;
    }
    .ai-field textarea { min-height:96px; resize:vertical; }
    .ai-badge { display:inline-flex; align-items:center; gap:6.4px; padding:6.4px 9.6px; border-radius:16px; background:#eef7ff; color:var(--primary,#219ebc); border:0.8px solid #d9ecff; margin-top:4.8px; }
    .criterion-row { display:flex; gap:6.4px; align-items:center; margin-bottom:6.4px; }
    .criterion-row input { flex:1; }
    .btn-remove-criterion { padding:6.4px 9.6px; background:#dc3545; color:#fff; border:none; border-radius:6.4px; cursor:pointer; }
    .btn-add-criterion { padding:6.4px 12.8px; background:#28a745; color:#fff; border:none; border-radius:6.4px; cursor:pointer; margin-top:6.4px; }
    @media(max-width:720px){.ai-grid{grid-template-columns:1fr;}}
    .swal2-container{z-index:20000 !important;}
  </style>

  <div id="csModalBackdrop" class="ai-modal-backdrop" aria-hidden="true">
  <div class="ai-modal" role="dialog" aria-modal="true" aria-labelledby="csModalTitle">
    <header>
      <h3 id="csModalTitle"><i class="fa-solid fa-file-lines"></i> تحليل دراسة الحالة</h3>
      <button id="btnCloseModal" class="ai-btn secondary"><i class="fa-solid fa-xmark"></i> إغلاق</button>
    </header>

    <div class="content">
      <form id="caseStudyForm" novalidate>
        <!-- ===== الأساسية ===== -->
        <div class="ai-grid">
          <div class="ai-field">
            <label for="teacher_name"><i class="fa-solid fa-user-tie"></i> اسم المعلم الطالب</label>
            <input type="text" id="teacher_name" name="teacher_name" required>
          </div>

          <div class="ai-field">
            <label for="grade_level"><i class="fa-solid fa-layer-group"></i> المرحلة التي يدرسها</label>
            <input type="text" id="grade_level" name="grade_level" placeholder="مثال: التاسع الأساسي" required>
          </div>

          <div class="ai-field full" id="caseSelectionField" hidden>
            <label for="selected_case_index"><i class="fa-solid fa-list"></i> اختر الحالة المراد تحليلها</label>
            <select id="selected_case_index" name="selected_case_index" required>
              <option value="">— اختر الحالة —</option>
            </select>
          </div>

          <div class="ai-field full" id="casePreviewField" hidden>
            <label><i class="fa-solid fa-eye"></i> معاينة الحالة المختارة</label>
            <div id="casePreview" style="padding: 12px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; min-height: 100px;">
              <p style="color: #6c757d; margin: 0;">اختر حالة لعرض معاينتها</p>
            </div>
          </div>

          <div class="ai-field full">
            <label for="analysis_nature"><i class="fa-solid fa-lightbulb"></i> طبيعة التحليل *</label>
            <select id="analysis_nature" name="analysis_nature" required>
              <option value="">— اختر طبيعة التحليل —</option>
              <option value="comprehensive">تحليل شامل ومتكامل</option>
              <option value="critical">تحليل نقدي يركز على التحديات</option>
              <option value="solution_oriented">تحليل يركز على الحلول والبدائل</option>
              <option value="reflective">تحليل تأملي يركز على التعلم</option>
              <option value="practical">تحليل عملي يركز على التطبيق</option>
              <option value="theoretical">تحليل نظري يركز على الأطر المفاهيمية</option>
            </select>
            <small class="ai-hint">اختر طبيعة التحليل لضمان تنوع الإجابات بين الطلبة</small>
          </div>

          <!-- حقل مخفي لنص الحالة (يُملأ تلقائياً من JSON) -->
          <input type="hidden" id="case_text" name="case_text">
          
          <!-- حاوية معايير المعلمين (يُملأ تلقائياً من JSON) -->
          <div class="ai-field full" id="criteriaContainer" style="display: none;">
            <label><i class="fa-solid fa-list-check"></i> معايير المعلمين</label>
            <div id="criteriaList" style="padding: 12px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
              <p style="color: #6c757d; margin: 0;">سيتم تحميل المعايير تلقائياً...</p>
            </div>
          </div>
        </div>

        <input type="hidden" id="type_key" name="type_key" value="case_study_v1">
        <input type="hidden" id="task_id" name="task_id">
      </form>
    </div>
    <footer>
      <button id="btnSubmit" class="ai-btn"><i class="fa-solid fa-paper-plane"></i> إرسال الطلب</button>
      <span class="ai-hint">يتم التنفيذ بالخلفية — لا حاجة لبقاء الصفحة مفتوحة.</span>
    </footer>
  </div>
</div>

