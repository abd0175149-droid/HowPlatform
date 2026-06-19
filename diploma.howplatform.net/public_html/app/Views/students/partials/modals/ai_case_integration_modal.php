  <style>
    .ai-case-integration-card {
      background: var(--surface, #fff);
      color: var(--text, #0a3247);
      border: 1px solid var(--border, #e1e5e9);
      border-radius: 14px;
      padding: 16px;
    }
    .ai-ci-header { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:8px; }
    .ai-ci-title { margin:0; font-size:20px; }
    .ai-ci-actions { display:flex; gap:8px; align-items:center; }
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
    .ai-field label { font-weight:500; color:var(--text,#0a3247); }
    @media(max-width:720px){.ai-grid{grid-template-columns:1fr;}}
    .swal2-container{z-index:20000 !important;}
  </style>

  <div id="ciModalBackdrop" class="ai-modal-backdrop" aria-hidden="true">
  <div class="ai-modal" role="dialog" aria-modal="true" aria-labelledby="ciModalTitle">
    <header>
      <h3 id="ciModalTitle"><i class="fa-solid fa-link"></i> دمج الحالة</h3>
      <button id="btnCloseModal" class="ai-btn secondary"><i class="fa-solid fa-xmark"></i> إغلاق</button>
    </header>

    <div class="content">
      <form id="caseIntegrationForm" novalidate>
        <!-- ===== الأساسية ===== -->
        <div class="ai-grid">
          <div class="ai-field">
            <label for="teacher_name"><i class="fa-solid fa-user-tie"></i> اسم المعلم الطالب</label>
            <input type="text" id="teacher_name" name="teacher_name" required>
          </div>

          <div class="ai-field">
            <label for="teacher_specialty"><i class="fa-solid fa-graduation-cap"></i> التخصص</label>
            <input type="text" id="teacher_specialty" name="teacher_specialty" required placeholder="مثال: الرياضيات، اللغة العربية">
          </div>

          <div class="ai-field">
            <label for="case_category"><i class="fa-solid fa-layer-group"></i> تصنيف الحالة</label>
            <select id="case_category" name="case_category" required>
              <option value="">— اختر التصنيف —</option>
              <option value="صعوبات التعلم الأكاديمية">صعوبات التعلم الأكاديمية</option>
              <option value="اضطرابات التواصل واللغة">اضطرابات التواصل واللغة</option>
              <option value="اضطرابات الانتباه والسلوك">اضطرابات الانتباه والسلوك</option>
              <option value="الإعاقات الحسية">الإعاقات الحسية</option>
              <option value="الإعاقات الحركية والصحية">الإعاقات الحركية والصحية</option>
              <option value="الإعاقة العقلية/النمائية">الإعاقة العقلية/النمائية</option>
              <option value="اضطراب طيف التوحد (ASD)">اضطراب طيف التوحد (ASD)</option>
              <option value="الاضطرابات الانفعالية والنفسية">الاضطرابات الانفعالية والنفسية</option>
              <option value="الموهبة والتفوق">الموهبة والتفوق</option>
              <option value="فروق فردية مرتبطة بالنوع الاجتماعي والسياق">فروق فردية مرتبطة بالنوع الاجتماعي والسياق</option>
            </select>
          </div>

          <div class="ai-field">
            <label for="case"><i class="fa-solid fa-list"></i> نوع الحالة</label>
            <select id="case" name="case" required disabled>
              <option value="">— اختر التصنيف أولاً —</option>
            </select>
          </div>

          <div class="ai-field full">
            <label for="case_description"><i class="fa-solid fa-file-lines"></i> وصف الحالة</label>
            <textarea id="case_description" name="case_description" required placeholder="ما هي تفاصيل الحالة التي تريد دمجها؟ اذكر السياق، التحديات، والاحتياجات التعليمية للطالب/الطلبة..." minlength="20"></textarea>
            <small class="ai-hint">ما هي تفاصيل الحالة التي تريد دمجها؟ اذكر السياق، التحديات، والاحتياجات التعليمية للطالب/الطلبة</small>
          </div>
        </div>

        <input type="hidden" id="type_key" name="type_key" value="case_integration_v1">
        <input type="hidden" id="task_id" name="task_id">
      </form>
    </div>
    <footer>
      <button id="btnSubmit" class="ai-btn"><i class="fa-solid fa-paper-plane"></i> إرسال الطلب</button>
      <span class="ai-hint">يتم التنفيذ بالخلفية — لا حاجة لبقاء الصفحة مفتوحة.</span>
    </footer>
  </div>
</div>

