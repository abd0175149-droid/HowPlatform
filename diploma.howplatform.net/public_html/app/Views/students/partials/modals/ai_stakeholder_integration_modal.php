  <style>
    .ai-stakeholder-integration-card {
      background: var(--surface, #fff);
      color: var(--text, #0a3247);
      border: 1px solid var(--border, #e1e5e9);
      border-radius: 14px;
      padding: 16px;
    }
    .ai-si-header { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:8px; }
    .ai-si-title { margin:0; font-size:20px; }
    .ai-si-actions { display:flex; gap:8px; align-items:center; }
    .ai-btn { padding:10px 14px; border-radius:10px; cursor:pointer; border:1px solid var(--primary,#219ebc); background:var(--primary,#219ebc); color:#fff; }
    .ai-btn.secondary { background:transparent; color:var(--primary,#219ebc); }
    .ai-hint { color: var(--text-light,#5a6c7d); font-size:14px; margin:6px 0 10px; }
    .ai-modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.35); display:none; align-items:center; justify-content:center; z-index:9999; }
    .ai-modal { width:min(900px,92vw); max-height:90vh; overflow:auto; background:#fff; border-radius:14px; border:1px solid #e1e5e9; box-shadow:0 12px 36px rgba(0,0,0,.18); }
    .ai-modal header, .ai-modal footer { padding:14px 16px; border-bottom:1px solid #e1e5e9; display:flex; align-items:center; justify-content:space-between; }
    .ai-modal footer { border-top:1px solid #e1e5e9; border-bottom:0; justify-content:flex-start; gap:8px; }
    .ai-modal .content { padding:14px 16px; }
    .ai-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .ai-grid .full { grid-column:1 / -1; }
    .ai-field { display:flex; flex-direction:column; gap:6px; }
    .ai-field input, .ai-field select, .ai-field textarea {
      border:1px solid #dcdfe4; border-radius:10px; padding:10px 12px; background:#fff;
    }
    .ai-field textarea { min-height:120px; resize:vertical; }
    @media(max-width:720px){.ai-grid{grid-template-columns:1fr;}}
    .swal2-container{z-index:20000 !important;}
  </style>

  <div id="siModalBackdrop" class="ai-modal-backdrop" aria-hidden="true">
  <div class="ai-modal" role="dialog" aria-modal="true" aria-labelledby="siModalTitle">
    <header>
      <h3 id="siModalTitle"><i class="fa-solid fa-users"></i> دمج أصحاب العلاقة</h3>
      <button id="btnCloseSiModal" class="ai-btn secondary"><i class="fa-solid fa-xmark"></i> إغلاق</button>
    </header>

    <div class="content">
      <form id="stakeholderIntegrationForm" novalidate>
        <!-- ===== الأساسية ===== -->
        <div class="ai-grid">
          <div class="ai-field">
            <label for="si_grade_level"><i class="fa-solid fa-layer-group"></i> الصف الدراسي *</label>
            <input type="text" id="si_grade_level" name="grade_level" required placeholder="مثال: العاشر الأساسي">
          </div>

          <div class="ai-field">
            <label for="si_subject_area"><i class="fa-solid fa-book"></i> المبحث الدراسي *</label>
            <input type="text" id="si_subject_area" name="subject_area" required placeholder="مثال: العلوم الحياتية">
          </div>

          <div class="ai-field full">
            <label for="si_learning_outcome"><i class="fa-solid fa-bullseye"></i> نتاج التعلم *</label>
            <textarea id="si_learning_outcome" name="learning_outcome" required placeholder="اكتب نتاج التعلم المستهدف..."></textarea>
          </div>

          <div class="ai-field full">
            <label for="si_stakeholder_role"><i class="fa-solid fa-user-tag"></i> صفة صاحب العلاقة *</label>
            <select id="si_stakeholder_role" name="stakeholder_role" required>
              <option value="">— اختر —</option>
              <option value="ولي أمر">ولي أمر</option>
              <option value="معلم زميل">معلم زميل</option>
              <option value="زائر خارجي">زائر خارجي</option>
            </select>
          </div>
          
          <!-- حاوية معايير المعلمين (يُملأ تلقائياً من JSON) -->
          <div class="ai-field full" id="siCriteriaContainer" style="display: none;">
            <label><i class="fa-solid fa-list-check"></i> معايير المعلمين</label>
            <div id="siCriteriaList" style="padding: 12px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
              <p style="color: #6c757d; margin: 0;">سيتم تحميل المعايير تلقائياً...</p>
            </div>
          </div>
        </div>

        <input type="hidden" id="si_type_key" name="type_key" value="stakeholder_integration_v1">
        <input type="hidden" id="si_task_id" name="task_id">
      </form>
    </div>
    <footer>
      <button id="btnSubmitSi" class="ai-btn"><i class="fa-solid fa-paper-plane"></i> إرسال الطلب</button>
      <span class="ai-hint">يتم التنفيذ بالخلفية — لا حاجة لبقاء الصفحة مفتوحة.</span>
    </footer>
  </div>
</div>
