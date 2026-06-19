/**
 * AI Inquiry Plan Evaluator Modal
 * واجهة ديناميكية لتقييم مخطط البحث الاستقصائي (Action Research Plan)
 *
 * الأقسام:
 *  - بيانات الطالب (ثابتة)
 *  - معلومات الالتزام (title_compliance, ref_count_check, table_rows_count)
 *  - 6 أقسام نصية مع عدّ كلمات فوري
 *  - جدول الحصص (2-3 صفوف ديناميكية)
 *  - قائمة المراجع (ديناميكية)
 *  - الملاحق (ديناميكية)
 */

export default {

    _container: null,

    // ─── حدود الكلمات المعتمدة ──────────────────────────────────────────────
    WORD_LIMITS: {
        section_1: { min: 150, max: 200, label: 'القسم الأول: المقدمة' },
        section_2: { min: 300, max: 400, label: 'القسم الثاني: مراجعة الأدب' },
        section_3: { min: 200, max: 280, label: 'القسم الثالث: نظرة عامة' },
        section_4: { min: 150, max: 200, label: 'القسم الرابع: الاعتبارات الأخلاقية' },
        section_5: { min: 200, max: 280, label: 'القسم الخامس: النتائج' },
        section_6: { min: 180, max: 250, label: 'القسم السادس: التأمل' },
    },

    // ─── بناء الواجهة الكاملة ───────────────────────────────────────────────
    buildUI(container, toolConfig) {
        this._container = container;
        console.log('[inquiry-plan-evaluator] buildUI called');

        let html = '';

        // ── القسم 1: البيانات الأساسية للطالب ──
        html += this.buildMetaSection();

        // ── القسم 2: معلومات الالتزام ──
        html += this.buildComplianceSection();

        // ── القسم 3: الأقسام النصية الستة ──
        html += this.buildTextSections();

        // ── القسم 4: جدول الحصص ──
        html += this.buildTableSection();

        // ── القسم 5: المراجع ──
        html += this.buildReferencesSection();

        // ── القسم 6: الملاحق ──
        html += this.buildAppendicesSection();

        container.innerHTML = html;
        this.attachEventListeners(container);

        console.log('[inquiry-plan-evaluator] UI built successfully');
    },

    // ─── بناء قسم البيانات الأساسية ────────────────────────────────────────
    buildMetaSection() {
        return `
<div class="ai-form-section">
  <h4>البيانات الأساسية</h4>
  <div class="ai-grid ai-grid-2">
    <div class="ai-field" data-field-key="subject_name">
      <label>المادة الدراسية <span style="color:red">*</span></label>
      <input type="text" name="subject_name" placeholder="مثال: الرياضيات، العلوم" required />
    </div>
    <div class="ai-field full" data-field-key="research_question" style="grid-column:1/-1">
      <label>سؤال البحث الاستقصائي <span style="color:red">*</span></label>
      <input type="text" name="research_question" placeholder="أدخل سؤال البحث الاستقصائي كما كتبه الطالب" required />
      <small>يُستخدم كمرجع للتحقق من التزام الطالب به في جميع الأقسام</small>
    </div>
  </div>
</div>`;
    },

    // ─── بناء قسم بيانات الالتزام ───────────────────────────────────────────
    buildComplianceSection() {
        return `
<div class="ai-form-section">
  <h4>بيانات الالتزام (يُدخلها المُقيِّم)</h4>
  <div class="ai-grid ai-grid-2">
    <div class="ai-field" data-field-key="title_compliance">
      <label>الالتزام بسؤال البحث في المخطط <span style="color:red">*</span></label>
      <select name="title_compliance" required>
        <option value="1">نعم — ملتزم بسؤال البحث كما هو</option>
        <option value="0">لا — يوجد انحراف أو تغيير في سؤال البحث</option>
      </select>
    </div>
    <div class="ai-field" data-field-key="ref_count_check">
      <label>عدد المراجع الكلي في مخطط الطالب <span style="color:red">*</span></label>
      <input type="number" name="ref_count_check" min="0" max="50" placeholder="مثال: 8" required />
      <small>الحد الأدنى المطلوب: 8 مراجع من 2018 فأحدث</small>
    </div>
    <div class="ai-field" data-field-key="table_rows_count">
      <label>عدد صفوف جدول الحصص في المخطط <span style="color:red">*</span></label>
      <input type="number" name="table_rows_count" min="0" max="10" placeholder="مثال: 2" required />
      <small>المطلوب: 2–3 صفوف (حصص دراسية فعلية)</small>
    </div>
  </div>
</div>`;
    },

    // ─── بناء الأقسام النصية الستة ──────────────────────────────────────────
    buildTextSections() {
        const sections = [
            { key: 'section_1', label: 'القسم الأول: المقدمة', min: 150, max: 200,
              hint: 'فقرتان: سبب الاهتمام بسؤال البحث + الربط بالمساقين / وصف سياق المدرسة والصف' },
            { key: 'section_2', label: 'القسم الثاني: مراجعة الأدب التربوي', min: 300, max: 400,
              hint: 'فقرتان: المراجع الداعمة للآلية التدريسية + كيفية توظيفها في فهم سؤال البحث' },
            { key: 'section_3', label: 'القسم الثالث: نظرة عامة على عملية الاستقصاء', min: 200, max: 280,
              hint: 'نص سردي يصف دورة الاستقصاء الكاملة (طرح السؤال ← القراءة ← التخطيط ← الأدلة ← التحليل)' },
            { key: 'section_4', label: 'القسم الرابع: الاعتبارات الأخلاقية', min: 150, max: 200,
              hint: 'السرية، الموافقات، الموضوعية، حماية بيانات الطلبة، الفروق الفردية' },
            { key: 'section_5', label: 'القسم الخامس: النتائج', min: 200, max: 280,
              hint: 'خطة تقييم التعلم كمياً ونوعياً، مبررات أدوات التقويم، منهجية تحليل البيانات' },
            { key: 'section_6', label: 'القسم السادس: النقاش التأملي', min: 180, max: 250,
              hint: 'فقرتان: أهمية الاستقصاء كموقف وممارسة / كيف سيطور هذا البحث ممارسة المعلم' },
        ];

        let html = `<div class="ai-form-section">
  <h4>نصوص أقسام المخطط</h4>
  <p style="color:#666;font-size:0.9em">الصق نص كل قسم من مخطط الطالب في الحقل المخصص — سيتم حساب عدد الكلمات تلقائياً.</p>`;

        for (const s of sections) {
            html += `
  <div class="inquiry-section" data-section-key="${s.key}" style="margin-bottom:20px;border:1px solid #e0e0e0;border-radius:8px;padding:14px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
      <label style="font-weight:600;color:#333">${s.label} <span style="color:red">*</span></label>
      <span class="wc-badge" id="wc-${s.key}" style="font-size:0.82em;padding:3px 10px;border-radius:12px;background:#f0f0f0;color:#555">
        0 كلمة | المطلوب: ${s.min}–${s.max}
      </span>
    </div>
    <small style="display:block;color:#888;margin-bottom:8px">${s.hint}</small>
    <textarea
      name="${s.key}"
      data-section="${s.key}"
      data-min="${s.min}"
      data-max="${s.max}"
      rows="8"
      required
      placeholder="الصق نص ${s.label} هنا..."
      style="width:100%;resize:vertical;font-size:0.92em"
    ></textarea>
  </div>`;
        }

        html += `</div>`;
        return html;
    },

    // ─── بناء قسم جدول الحصص ────────────────────────────────────────────────
    buildTableSection() {
        return `
<div class="ai-form-section">
  <h4>جدول الحصص الدراسية (القسم الثالث)</h4>
  <p style="color:#666;font-size:0.9em">أدخل 2–3 صفوف تمثّل الحصص الدراسية الفعلية المخططة.</p>
  <div id="table-rows-container"></div>
  <button type="button" id="btn-add-table-row" class="btn-add-part" style="margin-top:8px">
    <i class="fas fa-plus"></i> إضافة حصة
  </button>
</div>`;
    },

    // ─── بناء قسم المراجع ────────────────────────────────────────────────────
    buildReferencesSection() {
        return `
<div class="ai-form-section">
  <h4>قائمة مراجع الطالب</h4>
  <p style="color:#666;font-size:0.9em">
    انسخ والصق جميع المراجع الواردة في مخطط الطالب بصيغة MLA هنا.
    <br><b style="color:#c0392b">تذكير:</b> المراجع الإلزامية الأربعة: قراءة الاستقصاء كنهج، دليل المعلم–صورة الدرس، الإطار العام للمنهاج، معايير المعلمين (أكاديمية الملكة رانيا 2024).
  </p>
  <textarea name="references" rows="5" placeholder="الصق جميع المراجع هنا..." style="width:100%;resize:vertical;font-size:0.92em;padding:10px;border-radius:6px;border:1px solid #ccc;" required></textarea>
</div>`;
    },

    // ─── بناء قسم الملاحق ───────────────────────────────────────────────────
    buildAppendicesSection() {
        return `
<div class="ai-form-section">
  <h4>الملاحق</h4>
  <p style="color:#666;font-size:0.9em">انسخ والصق أسماء أو محتوى الملاحق الواردة في مخطط الطالب (دليل المعلم، خطط الدروس، أدوات التقويم...).</p>
  <textarea name="appendices" rows="5" placeholder="الصق تفاصيل الملاحق هنا..." style="width:100%;resize:vertical;font-size:0.92em;padding:10px;border-radius:6px;border:1px solid #ccc;" required></textarea>
</div>`;
    },

    // ─── إضافة مستمعات الأحداث ──────────────────────────────────────────────
    attachEventListeners(container) {

        // عدّ الكلمات للأقسام النصية
        container.addEventListener('input', (e) => {
            const ta = e.target;
            if (ta.tagName === 'TEXTAREA' && ta.dataset.section) {
                this.updateSectionWordCount(ta);
            }
        });

        // إضافة صف للجدول
        const btnTable = container.querySelector('#btn-add-table-row');
        if (btnTable) {
            btnTable.addEventListener('click', () => this.addTableRow(container));
        }

        // حذف عناصر ديناميكية لجدول الحصص
        container.addEventListener('click', (e) => {
            if (e.target.closest('.btn-remove-row')) {
                e.target.closest('.dynamic-row').remove();
            }
        });

        // إضافة صفّين افتراضيين للجدول
        this.addTableRow(container);
        this.addTableRow(container);
    },

    // ─── إضافة صف جدول ──────────────────────────────────────────────────────
    addTableRow(container) {
        const tableContainer = container.querySelector('#table-rows-container');
        if (!tableContainer) return;

        const idx = tableContainer.querySelectorAll('.dynamic-row').length + 1;
        const row = document.createElement('div');
        row.className = 'dynamic-row';
        row.style.cssText = 'border:1px solid #e0e0e0;border-radius:8px;padding:12px;margin-bottom:10px';
        row.innerHTML = `
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
  <b>الحصة ${idx}</b>
  <button type="button" class="btn-remove-row" style="background:none;border:none;color:#c0392b;cursor:pointer" title="حذف"><i class="fas fa-trash"></i></button>
</div>
<div class="ai-grid ai-grid-2">
  <div class="ai-field"><label>عنوان الحصة</label><input type="text" name="table_lesson_title_${idx}" placeholder="عنوان مفصّل يوضح الآلية" /></div>
  <div class="ai-field"><label>النتاجات</label><input type="text" name="table_outcomes_${idx}" placeholder="نتاجات هذه الحصة" /></div>
  <div class="ai-field"><label>الاستراتيجية وخطوات التطبيق</label><input type="text" name="table_strategy_${idx}" placeholder="الاستراتيجية التدريسية" /></div>
  <div class="ai-field"><label>أدوات جمع البيانات</label><input type="text" name="table_tools_${idx}" placeholder="بطاقة ملاحظة / ورقة عمل / بطاقة خروج" /></div>
  <div class="ai-field"><label>استراتيجية التقويم</label><input type="text" name="table_assessment_${idx}" placeholder="استراتيجية التقويم الواقعي" /></div>
  <div class="ai-field"><label>مصادر التعلم</label><input type="text" name="table_resources_${idx}" placeholder="الكتاب المدرسي، مواد رقمية..." /></div>
</div>`;
        tableContainer.appendChild(row);
    },



    // ─── حساب وتحديث عدد الكلمات لقسم ─────────────────────────────────────
    updateSectionWordCount(textarea) {
        const key  = textarea.dataset.section;
        const min  = parseInt(textarea.dataset.min)  || 0;
        const max  = parseInt(textarea.dataset.max)  || 9999;
        const text = textarea.value.trim();
        const wc   = this.countWords(text);

        const badge = document.getElementById(`wc-${key}`);
        if (!badge) return;

        badge.textContent = `${wc} كلمة | المطلوب: ${min}–${max}`;

        if (wc === 0) {
            badge.style.background = '#f0f0f0';
            badge.style.color      = '#777';
        } else if (wc < min) {
            badge.style.background = '#fdecea';
            badge.style.color      = '#c0392b';
        } else if (wc > max) {
            badge.style.background = '#fff3e0';
            badge.style.color      = '#e67e22';
        } else {
            badge.style.background = '#e8f5e9';
            badge.style.color      = '#27ae60';
        }
    },

    // ─── حساب الكلمات ────────────────────────────────────────────────────────
    countWords(text) {
        if (!text) return 0;
        const words = text.match(/\S+/g);
        return words ? words.length : 0;
    },

    // ─── جمع البيانات ────────────────────────────────────────────────────────
    collectPayload() {
        const container = this._container;
        if (!container) return {};

        const payload = {};

        // ── الحقول الثابتة ──
        const staticFields = ['subject_name', 'research_question',
                              'title_compliance', 'ref_count_check', 'table_rows_count'];
        for (const key of staticFields) {
            const el = container.querySelector(`[name="${key}"]`);
            if (el) payload[key] = el.value.trim();
        }

        // ── الأقسام النصية + عدد الكلمات ──
        const wordCounts = {};
        const sectionKeys = ['section_1', 'section_2', 'section_3',
                             'section_4', 'section_5', 'section_6'];
        for (const key of sectionKeys) {
            const ta = container.querySelector(`textarea[name="${key}"]`);
            if (ta) {
                payload[key] = ta.value.trim();
                wordCounts[key] = this.countWords(ta.value.trim());
            }
        }
        payload.word_counts = wordCounts;

        // ── جدول الحصص ──
        const tableRows = [];
        container.querySelectorAll('#table-rows-container .dynamic-row').forEach((row, i) => {
            const idx = i + 1;
            const get = (name) => (row.querySelector(`[name="${name}"]`)?.value?.trim() || '');
            tableRows.push({
                lesson_title:         get(`table_lesson_title_${idx}`),
                learning_outcomes:    get(`table_outcomes_${idx}`),
                teaching_strategy:    get(`table_strategy_${idx}`),
                data_collection_tools:get(`table_tools_${idx}`),
                assessment_strategy:  get(`table_assessment_${idx}`),
                learning_resources:   get(`table_resources_${idx}`),
            });
        });
        payload.section_3_table = tableRows;

        // ── المراجع ──
        const refsText = container.querySelector('[name="references"]')?.value?.trim() || '';
        payload.references = refsText ? refsText.split(/\r?\n/).filter(line => line.trim() !== '') : [];

        // ── الملاحق ──
        const appsText = container.querySelector('[name="appendices"]')?.value?.trim() || '';
        payload.appendices = appsText ? appsText.split(/\r?\n/).filter(line => line.trim() !== '') : [];

        console.log('[inquiry-plan-evaluator] Collected payload:', payload);
        const validation = this.validatePayload(payload);
        
        if (!validation.valid) {
            // Can optionally display errors here or rely on the factory's notifyUser
            console.warn('[inquiry-plan-evaluator] Validation failed:', validation.errors);
        }
        
        return { payload, valid: validation.valid };
    },

    // ─── تعبئة الحقول من payload (للتعديل) ─────────────────────────────────
    populateFields(payload) {
        const container = this._container;
        if (!container) return;

        // الحقول الثابتة
        const staticFields = ['subject_name', 'research_question',
                              'title_compliance', 'ref_count_check', 'table_rows_count',
                              'references', 'appendices'];
        for (const key of staticFields) {
            const el = container.querySelector(`[name="${key}"]`);
            if (el && payload[key] !== undefined) {
                el.value = payload[key];
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        // الأقسام النصية
        ['section_1','section_2','section_3','section_4','section_5','section_6'].forEach(key => {
            const ta = container.querySelector(`textarea[name="${key}"]`);
            if (ta && payload[key]) {
                ta.value = payload[key];
                this.updateSectionWordCount(ta);
            }
        });
    },

    // ─── التحقق من البيانات ──────────────────────────────────────────────────
    validatePayload(payload) {
        const errors = [];

        if (!payload.subject_name?.trim())         errors.push('المادة الدراسية مطلوبة');
        if (!payload.research_question?.trim())    errors.push('سؤال البحث مطلوب');

        // التحقق من وجود نص في الأقسام الأساسية
        const requiredSections = ['section_1', 'section_2', 'section_3',
                                  'section_4', 'section_5', 'section_6'];
        for (const key of requiredSections) {
            if (!payload[key]?.trim()) {
                errors.push(`نص ${this.WORD_LIMITS[key]?.label || key} مطلوب`);
            }
        }

        return { valid: errors.length === 0, errors };
    }
};
