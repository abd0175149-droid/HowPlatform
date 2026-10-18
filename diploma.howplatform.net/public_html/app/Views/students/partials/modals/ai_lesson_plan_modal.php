  <style>
    .ai-lesson-plan-card {
      background: var(--surface, #fff);
      color: var(--text, #0a3247);
      border: 0.8px solid var(--border, #e1e5e9);
      border-radius: 11.2px;
      padding: 12.8px;
    }
    .ai-lp-header { display:flex; align-items:center; justify-content:space-between; gap:9.6px; margin-bottom:6.4px; }
    .ai-lp-title { margin:0; font-size:16px; }
    .ai-lp-actions { display:flex; gap:6.4px; align-items:center; }
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
    .ai-field textarea { min-height:65.6px; resize:vertical; }
    .ai-badge { display:inline-flex; align-items:center; gap:6.4px; padding:6.4px 9.6px; border-radius:16px; background:#eef7ff; color:var(--primary,#219ebc); border:0.8px solid #d9ecff; margin-top:4.8px; }
    @media(max-width:720px){.ai-grid{grid-template-columns:1fr;}}

    /* ===== Toggle Switch ===== */
    .switch{position:relative;display:inline-flex;align-items:center;gap:.5rem;cursor:pointer;}
    .switch input{display:none;}
    .switch .track{width:35.2px;height:19.2px;border-radius:799.2px;background:#d9e2ec;border:0.8px solid #c6d3e0;position:relative;transition:.2s;}
    .switch .thumb{position:absolute;top:50%;left:1.6px;transform:translateY(-50%);width:16px;height:16px;background:#fff;border-radius:50%;box-shadow:0 0.8px 2.4px rgba(0,0,0,.15);transition:.2s;}
    .switch input:checked+.track{background:var(--primary,#219ebc);border-color:var(--primary,#219ebc);}
    .switch input:checked+.track .thumb{left:17.6px;}

    /* ===== Advanced section ===== */
    .advanced-section{padding:1rem 1.25rem;background:#fafafa;border-radius:9.6px;box-shadow:0 0.8px 3.2px rgba(0,0,0,.05);margin-top:.5rem;}
    .advanced-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(192px,1fr));gap:1rem 1.2rem;margin-bottom:1.5rem;}
    .advanced-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;}
    .advanced-header .title{display:flex;align-items:center;gap:.5rem;font-weight:600;color:var(--primary,#219ebc);}
    .fa-gear{color:var(--primary,#219ebc);}

    /* ===== Chips ===== */
    .chips-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.5rem;}
    @media(max-width:920px){.chips-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
    .chip{background:#f5f7fb;border:0.8px solid #dfe7f3;border-radius:14.4px;padding:6.4px 8px;text-align:center;cursor:pointer;transition:.2s;}
    .chip:hover{transform:translateY(-1.6px);box-shadow:0 1.6px 4.8px rgba(0,0,0,.07);}
    .chip.active{background:var(--primary,#219ebc);color:#fff;border-color:var(--primary,#219ebc);}
    .chip.deny{animation:chipDeny .25s ease;}
    @keyframes chipDeny{0%,100%{transform:translateX(0);}25%{transform:translateX(-2.4px);}75%{transform:translateX(2.4px);}}
    .swal2-container{z-index:20000 !important;}
    .advanced{
    border: 0.8px dashed var(--primary);
    padding: 8px;
    margin-bottom: 8px;
    border-radius: 9.6px;}
  </style>

  <div id="lpModalBackdrop" class="ai-modal-backdrop" aria-hidden="true">
  <div class="ai-modal" role="dialog" aria-modal="true" aria-labelledby="lpModalTitle">
    <header>
      <h3 id="lpModalTitle"><i class="fa-solid fa-chalkboard-user"></i> بيانات الحصّة التعليمية</h3>
      <button id="btnCloseModal" class="ai-btn secondary"><i class="fa-solid fa-xmark"></i> إغلاق</button>
    </header>

    <div class="content">
      <form id="lessonPlanForm" novalidate>
        <!-- ===== الأساسية ===== -->
        <div class="ai-grid">
          <div class="ai-field">
            <label for="teacher_name"><i class="fa-solid fa-user-tie"></i> اسم المعلم</label>
            <input type="text" id="teacher_name" name="teacher_name" required>
          </div>

          <div class="ai-field">
            <label for="teacher_gender"><i class="fa-solid fa-venus-mars"></i> جنس المعلم</label>
            <select id="teacher_gender" name="teacher_gender" required>
              <option value="">— اختر —</option>
              <option value="male">ذكر</option>
              <option value="female">أنثى</option>
            </select>
          </div>

          <div class="ai-field">
            <label for="school_name"><i class="fa-solid fa-school"></i> اسم المدرسة</label>
            <input type="text" id="school_name" name="school_name" required>
          </div>

          <div class="ai-field">
            <label for="grade_level"><i class="fa-solid fa-layer-group"></i> الصف الدراسي</label>
            <input type="text" id="grade_level" name="grade_level" placeholder="مثال: التاسع الأساسي" required>
          </div>

          <div class="ai-field">
            <label for="subject_name"><i class="fa-solid fa-book-open"></i> المادة / المبحث</label>
            <input type="text" id="subject_name" name="subject_name" required>
          </div>

          <div class="ai-field">
            <label for="student_gender"><i class="fa-solid fa-children"></i> جنس الطلبة</label>
            <select id="student_gender" name="student_gender" required>
              <option value="">— اختر —</option>
              <option value="male">ذكور</option>
              <option value="female">إناث</option>
              <option value="mixed">مختلط</option>
            </select>
          </div>

          <div class="ai-field">
            <label for="unit_name"><i class="fa-solid fa-diagram-project"></i> الوحدة الدراسية</label>
            <input type="text" id="unit_name" name="unit_name">
          </div>

          <div class="ai-field">
            <label for="lesson_title"><i class="fa-solid fa-chalkboard"></i> اسم الدرس</label>
            <input type="text" id="lesson_title" name="lesson_title" required>
          </div>

          <div class="ai-field full">
            <label for="topic_summary"><i class="fa-regular fa-file-lines"></i> وصف مختصر (≥100 حرف)</label>
            <textarea id="topic_summary" name="topic_summary" minlength="100" required></textarea>
          </div>

          <div class="ai-field">
            <label for="outcomes_count"><i class="fa-solid fa-list-check"></i> عدد النتاجات</label>
            <input type="number" id="outcomes_count" name="outcomes_count" min="2" value="2" required>
          </div>

          <div class="ai-field full">
            <label for="outcomes_description"><i class="fa-regular fa-rectangle-list"></i> وصف عام للنتاجات</label>
            <textarea id="outcomes_description" name="outcomes_description" placeholder="اكتب وصفًا عامًا للنتاجات التعليمية المتوقعة"></textarea>
          </div>

          <div class="ai-field full">
            <button type="button" id="toggleAdvanced" class="ai-btn secondary"><i class="fa-solid fa-gear"></i> الخيارات المتقدمة</button>
          </div>
        </div>

        <!-- ===== المتقدمة ===== -->
        <div id="advancedOptions" class="advanced-section" hidden>
          <div class="advanced-header"><div class="title"><i class="fa-solid fa-gear"></i> الإعدادات المتقدمة</div></div>
          <div class="advanced-grid advanced">
            <div class="ai-field">
              <label for="duration_minutes"><i class="fa-regular fa-clock"></i> مدة الحصة (دقائق)</label>
              <input type="number" id="duration_minutes" name="duration_minutes" min="10" max="120" value="40">
            </div>

            <div class="ai-field">
              <label for="class_size"><i class="fa-solid fa-users"></i> عدد الطلبة في الشعبة</label>
              <input type="number" id="class_size" name="class_size" min="5" max="60" value="30">
            </div>

            <div class="ai-field">
              <label for="focus_axis"><i class="fa-solid fa-bullseye"></i> محور التركيز في الخطة</label>
              <select id="focus_axis" name="focus_axis">
                <option value="">— اختر —</option>
                <option value="التمييز">التمييز</option>
                <option value="العلاقات الصفية/بيئة داعمة">العلاقات الصفية/بيئة داعمة</option>
                <option value="الدمج والتكييف">الدمج والتكييف</option>
              </select>
            </div>

            <!-- Toggle -->
            <div class="ai-field ">
              <label class="switch" for="sen_present">
                <input type="checkbox" id="sen_present" name="sen_present">
                <span class="track"><span class="thumb"></span></span>
                <span>وجود طلبة ذوي احتياجات خاصة</span>
              </label>
            </div>

            <div class="ai-field" id="senSupportField" hidden>
              <label for="sen_support"><i class="fa-solid fa-hand-holding-heart"></i> نوع الدعم المقدّم</label>
              <select id="sen_support" name="sen_support">
                <option value="">— اختر نوع الدعم —</option>
                <option value="تكييف زمني">تكييف زمني (وقت إضافي)</option>
                <option value="تبسيط التعليمات">تبسيط التعليمات والمفاهيم</option>
                <option value="وسائل بصرية">استخدام وسائل بصرية أو سمعية</option>
                <option value="مساعدة فردية">مساعدة فردية داخل الصف</option>
                <option value="مواد بديلة مبسطة">مواد تعليمية بديلة مبسطة</option>
                <option value="بيئة صفية هادئة">تهيئة بيئة صفية هادئة</option>
                <option value="أخرى">أخرى (يُذكر أدناه)</option>
              </select>
            </div>
            
            <div class="ai-field" id="senSupportOtherField" hidden>
              <label for="sen_support_other"><i class="fa-solid fa-pen-to-square"></i> نوع الدعم (أخرى)</label>
              <input type="text" id="sen_support_other" name="sen_support_other" placeholder="مثال: طباعة أوراق مكبرة أو مترجم لغة إشارة…">
            </div>

            <div class="ai-field full">
              <label for="learning_assets"><i class="fa-solid fa-box-archive"></i> موارد التعلّم المتاحة في الصف</label>
              <textarea id="learning_assets" name="learning_assets" placeholder="مثال: سبورة، عارض بيانات، أوراق عمل، أجهزة لوحية، روابط تعليمية…"></textarea>
            </div>

            <div class="ai-field full">
              <label for="constraints"><i class="fa-solid fa-file-signature"></i> القيود أو الشروط (اختياري)</label>
              <textarea id="constraints" name="constraints" placeholder="مثال: استخدم مطبوعات ورقية  ..."></textarea>
            </div>
          </div>

          <!-- استراتيجيات -->
          <fieldset class="ai-field full advanced">
            <legend><i class="fa-solid fa-chalkboard-user"></i> استراتيجيات التدريس (حتى 3)</legend>
            <div class="chips-grid" id="teachingStrategies">
              <div class="chip" data-value="العصف الذهني">العصف الذهني</div>
              <div class="chip" data-value="فكر–زاوج–شارك">فكر–زاوج–شارك</div>
              <div class="chip" data-value="التعلّم التعاوني">التعلّم التعاوني</div>
              <div class="chip" data-value="العرض العملي">العرض العملي</div>
              <div class="chip" data-value="الاستقصاء العلمي">الاستقصاء العلمي</div>
              <div class="chip" data-value="لعب الأدوار">لعب الأدوار</div>
              <div class="chip" data-value="الحوار السقراطي">الحوار السقراطي</div>
              <div class="chip" data-value="التعلّم بالمحطات">التعلّم بالمحطات</div>
              <div class="chip" data-value="نظام التفكير المرئي">نظام التفكير المرئي</div>
              <div class="chip" data-value="مشي المعرض">مشي المعرض</div>
            </div>
          </fieldset>

          <fieldset class="ai-field full advanced">
            <legend><i class="fa-solid fa-clipboard-check"></i> استراتيجيات التقويم (حتى 3)</legend>
            <div class="chips-grid" id="assessmentMethods">
              <div class="chip" data-value="التقويم القبلي (التشخيصي)">التقويم القبلي (التشخيصي)</div>
              <div class="chip" data-value="التقويم البنائي المستمر">التقويم البنائي المستمر</div>
              <div class="chip" data-value="الملاحظة الصفّية">الملاحظة الصفّية المنظمة</div>
              <div class="chip" data-value="الأسئلة الشفوية التفاعلية">الأسئلة الشفوية التفاعلية</div>
              <div class="chip" data-value="التفكير بصوت عالٍ">التفكير بصوت عالٍ</div>
              <div class="chip" data-value="التعلّم التعاوني">التعلّم التعاوني</div>
              <div class="chip" data-value="التقييم الذاتي">التقييم الذاتي</div>
              <div class="chip" data-value="مهام أدائية قصيرة">مهام أدائية قصيرة</div>
              <div class="chip" data-value="العروض التقديمية القصيرة">العروض التقديمية القصيرة</div>
              <div class="chip" data-value="خرائط المفاهيم الختامية">خرائط المفاهيم الختامية</div>
            </div>
          </fieldset>
        </div>
      </form>
    </div>
    <footer>
      <button id="btnSubmit" class="ai-btn"><i class="fa-solid fa-paper-plane"></i> إرسال الطلب</button>
      <span class="ai-hint">يتم التنفيذ بالخلفية — لا حاجة لبقاء الصفحة مفتوحة.</span>
    </footer>
  </div>
</div>
