/**
 * AI Article Evaluator Modal
 * واجهة ديناميكية لتقييم المقالات الأكاديمية
 */

export default {
    // حفظ مرجع للـ container
    _container: null,

    /**
     * بناء الواجهة
     */
    buildUI(container, toolConfig) {
        // حفظ مرجع للـ container للاستخدام لاحقاً
        this._container = container;
        console.log('[article-evaluator] buildUI called with config:', toolConfig);
        
        const formSections = toolConfig?.form?.sections || [];
        let html = '';

        // القسم الأول: الحقول الثابتة
        const section1 = formSections[0];
        if (section1) {
            html += this.buildStaticSection(section1);
            console.log('[article-evaluator] Static section built');
        } else {
            console.warn('[article-evaluator] No static section found in config');
        }

        // القسم الثاني: الأقسام الديناميكية
        html += this.buildDynamicSections();
        console.log('[article-evaluator] Dynamic sections built');

        container.innerHTML = html;
        console.log('[article-evaluator] HTML injected into container');

        // إضافة مستمعات الأحداث
        this.attachEventListeners(container);
        console.log('[article-evaluator] Event listeners attached');
        
        // التحقق من أن العناصر تم إنشاؤها
        const inputs = container.querySelectorAll('input');
        const selects = container.querySelectorAll('select');
        const textareas = container.querySelectorAll('textarea');
        console.log('[article-evaluator] Elements created:', {
            inputs: inputs.length,
            selects: selects.length,
            textareas: textareas.length
        });
    },

    /**
     * بناء القسم الثابت (الحقول الأساسية)
     */
    buildStaticSection(section) {
        let html = '<div class="ai-form-section">';
        html += `<h4>${section.title || 'بيانات أساسية'}</h4>`;
        
        if (section.description) {
            html += `<p>${section.description}</p>`;
        }

        const layout = section.layout || 'grid-2';
        html += `<div class="ai-grid ai-${layout}">`;

        for (const field of section.fields || []) {
            html += this.buildField(field);
        }

        html += '</div></div>';
        return html;
    },

    /**
     * بناء حقل واحد
     */
    buildField(field) {
        const required = field.required ? 'required' : '';
        const placeholder = field.placeholder || '';
        const full = field.full ? 'full' : '';

        let html = `<div class="ai-field ${full}" data-field-key="${field.key}">`;
        html += `<label>${field.label}${field.required ? ' <span style="color:red">*</span>' : ''}</label>`;

        if (field.type === 'text' || field.type === 'number') {
            const type = field.type || 'text';
            const min = field.min !== undefined ? `min="${field.min}"` : '';
            const max = field.max !== undefined ? `max="${field.max}"` : '';
            html += `<input type="${type}" name="${field.key}" placeholder="${placeholder}" ${required} ${min} ${max} />`;
        } else if (field.type === 'select') {
            html += `<select name="${field.key}" ${required}>`;
            for (const option of field.options || []) {
                html += `<option value="${option.value}">${option.label}</option>`;
            }
            html += '</select>';
        } else if (field.type === 'chips') {
            html += '<div class="ai-chips" data-chips-key="' + field.key + '">';
            for (const option of field.options || []) {
                html += `<div class="ai-chip" data-value="${option}">${option}</div>`;
            }
            html += '</div>';
        } else if (field.type === 'textarea') {
            html += `<textarea name="${field.key}" placeholder="${placeholder}" ${required}></textarea>`;
        }

        if (field.hint) {
            html += `<small>${field.hint}</small>`;
        }

        html += '</div>';
        return html;
    },

    /**
     * بناء الأقسام الديناميكية (المقدمة، العرض، الخاتمة)
     */
    buildDynamicSections() {
        let html = '<div class="ai-form-section">';
        html += '<h4>نص المقال</h4>';
        html += '<p>قم بإضافة أجزاء لكل قسم من المقال (المقدمة، العرض، الخاتمة)</p>';
        html += '<div id="article-sections-container">';

        // المقدمة
        html += this.buildArticleSection('introduction', 'المقدمة', 1);

        // العرض
        html += this.buildArticleSection('body', 'العرض', 1);

        // الخاتمة
        html += this.buildArticleSection('conclusion', 'الخاتمة', 1);

        html += '</div></div>';
        return html;
    },

    /**
     * بناء قسم من المقال (مقدمة/عرض/خاتمة)
     */
    buildArticleSection(key, title, initialParts = 1) {
        let html = `<div class="article-section" data-section-key="${key}">`;
        html += '<div class="section-header">';
        html += `<h5 contenteditable="true" class="section-title">${title}</h5>`;
        html += `<div class="section-info">`;
        html += `<span class="word-count">عدد الكلمات: <strong class="word-count-value">0</strong></span>`;
        html += `</div>`;
        html += '</div>';
        
        html += `<div class="section-parts">`;
        
        // إضافة جزء واحد افتراضياً
        for (let i = 0; i < initialParts; i++) {
            html += this.buildPartHTML(key, i + 1);
        }
        
        html += '</div>';
        
        html += '<div class="section-actions">';
        html += `<button type="button" class="btn-add-part" data-section="${key}">`;
        html += '<i class="fas fa-plus"></i> إضافة جزء</button>';
        html += '</div>';
        html += '</div>';
        
        return html;
    },

    /**
     * بناء HTML لجزء واحد
     */
    buildPartHTML(sectionKey, partIndex) {
        return `
<div class="article-part" data-part-index="${partIndex}">
    <div class="part-header">
        <input type="text" class="part-title" placeholder="عنوان الجزء" value="${this.getDefaultPartTitle(sectionKey, partIndex)}" />
        <button type="button" class="btn-remove-part" title="حذف الجزء">
            <i class="fas fa-trash"></i>
        </button>
    </div>
    <textarea class="part-text" placeholder="اكتب نص الجزء هنا..." rows="8"></textarea>
    <div class="part-info">
        <span class="part-word-count">عدد الكلمات: <strong>0</strong></span>
    </div>
</div>`;
    },

    /**
     * الحصول على عنوان افتراضي للجزء
     */
    getDefaultPartTitle(sectionKey, partIndex) {
        const titles = {
            introduction: 'مقدمة جزء ',
            body: 'عرض ',
            conclusion: 'خاتمة جزء '
        };
        return (titles[sectionKey] || 'جزء ') + partIndex;
    },

    /**
     * إضافة مستمعات الأحداث
     */
    attachEventListeners(container) {
        // أزرار إضافة الأجزاء
        container.querySelectorAll('.btn-add-part').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const sectionKey = e.currentTarget.dataset.section;
                this.addPart(container, sectionKey);
            });
        });

        // أزرار حذف الأجزاء (delegation)
        container.addEventListener('click', (e) => {
            if (e.target.closest('.btn-remove-part')) {
                const partEl = e.target.closest('.article-part');
                this.removePart(container, partEl);
            }
        });

        // تحديث عدد الكلمات عند الكتابة
        container.addEventListener('input', (e) => {
            if (e.target.classList.contains('part-text')) {
                this.updateWordCount(container, e.target);
            }
        });

        // Chips selection
        container.querySelectorAll('.ai-chips .ai-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                chip.classList.toggle('active');
            });
        });

        // حساب عدد الكلمات الأولي
        this.updateAllWordCounts(container);
    },

    /**
     * إضافة جزء جديد
     */
    addPart(container, sectionKey) {
        const section = container.querySelector(`.article-section[data-section-key="${sectionKey}"]`);
        if (!section) return;

        const partsContainer = section.querySelector('.section-parts');
        const currentParts = partsContainer.querySelectorAll('.article-part').length;
        const newPartIndex = currentParts + 1;

        const partHTML = this.buildPartHTML(sectionKey, newPartIndex);
        partsContainer.insertAdjacentHTML('beforeend', partHTML);

        // تحديث عدد الكلمات
        this.updateSectionWordCount(container, sectionKey);
    },

    /**
     * حذف جزء
     */
    removePart(container, partEl) {
        const section = partEl.closest('.article-section');
        const partsContainer = section.querySelector('.section-parts');
        const currentParts = partsContainer.querySelectorAll('.article-part').length;

        // عدم السماح بحذف آخر جزء
        if (currentParts <= 1) {
            alert('يجب أن يحتوي كل قسم على جزء واحد على الأقل');
            return;
        }

        partEl.remove();

        // إعادة ترقيم الأجزاء
        const sectionKey = section.dataset.sectionKey;
        this.renumberParts(section, sectionKey);

        // تحديث عدد الكلمات
        this.updateSectionWordCount(container, sectionKey);
    },

    /**
     * إعادة ترقيم الأجزاء بعد الحذف
     */
    renumberParts(section, sectionKey) {
        const parts = section.querySelectorAll('.article-part');
        parts.forEach((part, index) => {
            part.dataset.partIndex = index + 1;
            const titleInput = part.querySelector('.part-title');
            if (titleInput && !titleInput.value.trim()) {
                titleInput.value = this.getDefaultPartTitle(sectionKey, index + 1);
            }
        });
    },

    /**
     * تحديث عدد الكلمات لجزء واحد
     */
    updateWordCount(container, textarea) {
        const part = textarea.closest('.article-part');
        const text = textarea.value.trim();
        const wordCount = this.countWords(text);

        const countEl = part.querySelector('.part-word-count strong');
        if (countEl) {
            countEl.textContent = wordCount;
        }

        // تحديث عدد الكلمات للقسم
        const section = part.closest('.article-section');
        const sectionKey = section.dataset.sectionKey;
        this.updateSectionWordCount(container, sectionKey);
    },

    /**
     * تحديث عدد الكلمات لقسم كامل
     */
    updateSectionWordCount(container, sectionKey) {
        const section = container.querySelector(`.article-section[data-section-key="${sectionKey}"]`);
        if (!section) return;

        let totalWords = 0;
        section.querySelectorAll('.part-text').forEach(textarea => {
            totalWords += this.countWords(textarea.value.trim());
        });

        const countEl = section.querySelector('.word-count-value');
        if (countEl) {
            countEl.textContent = totalWords;
        }
    },

    /**
     * تحديث عدد الكلمات لجميع الأقسام
     */
    updateAllWordCounts(container) {
        ['introduction', 'body', 'conclusion'].forEach(key => {
            this.updateSectionWordCount(container, key);
        });
    },

    /**
     * حساب عدد الكلمات في نص
     */
    countWords(text) {
        if (!text) return 0;
        const words = text.match(/\S+/g);
        return words ? words.length : 0;
    },

    /**
     * جمع البيانات من الواجهة
     */
    collectPayload() {
        const payload = {};

        // استخدام الـ container المحفوظ
        const container = this._container;
        if (!container) {
            console.error('[article-evaluator] Container not found - buildUI was not called');
            return payload;
        }

        console.log('[article-evaluator] Container found, collecting fields...');

        // جمع الحقول الثابتة
        const fields = container.querySelectorAll('.ai-field input, .ai-field select, .ai-field textarea');
        console.log('[article-evaluator] Found static fields:', fields.length);
        
        fields.forEach((input, index) => {
            const name = input.name || input.closest('[data-field-key]')?.dataset?.fieldKey;
            const value = input.value;
            console.log(`[article-evaluator] Field ${index}:`, {
                name: name,
                value: value,
                type: input.tagName,
                hasName: !!input.name
            });
            
            if (name) {
                // تخطي القيم الفارغة للـ select (الخيار الافتراضي "— اختر —")
                if (input.tagName === 'SELECT' && input.value === '') {
                    console.log(`[article-evaluator] Skipping empty select: ${name}`);
                    return;
                }
                payload[name] = input.value;
            }
        });

        // جمع chips (formatting_compliance) كـ Object بقيم 0 و 1
        container.querySelectorAll('.ai-chips').forEach(chipsContainer => {
            const key = chipsContainer.dataset.chipsKey;
            
            if (key === 'formatting_compliance') {
                // لحقل formatting_compliance: إرسال Object بقيم 0 و 1
                const complianceObj = {};
                chipsContainer.querySelectorAll('.ai-chip').forEach(chip => {
                    const value = chip.dataset.value;
                    complianceObj[value] = chip.classList.contains('active') ? 1 : 0;
                });
                payload[key] = complianceObj;
                console.log(`[article-evaluator] Formatting compliance (0/1):`, complianceObj);
            } else {
                // للحقول الأخرى: الطريقة العادية (Array)
                const selected = [];
                chipsContainer.querySelectorAll('.ai-chip.active').forEach(chip => {
                    selected.push(chip.dataset.value);
                });
                payload[key] = selected;
                console.log(`[article-evaluator] Chips ${key}:`, selected);
            }
        });

        // جمع عدد الكلمات لكل قسم
        const wordCounts = {};
        
        // جمع الأقسام الديناميكية
        ['introduction', 'body', 'conclusion'].forEach(sectionKey => {
            const section = container.querySelector(`.article-section[data-section-key="${sectionKey}"]`);
            if (!section) {
                console.warn(`[article-evaluator] Section ${sectionKey} not found in container`);
                return;
            }

            // حساب عدد الكلمات للقسم
            let sectionWordCount = 0;
            section.querySelectorAll('.part-text').forEach(textarea => {
                sectionWordCount += this.countWords(textarea.value.trim());
            });
            
            wordCounts[sectionKey] = sectionWordCount;
            console.log(`[article-evaluator] Section ${sectionKey} word count:`, sectionWordCount);

            const sectionData = {
                title: section.querySelector('.section-title')?.textContent?.trim() || '',
                word_count: sectionWordCount,
                parts: []
            };

            const parts = section.querySelectorAll('.article-part');
            console.log(`[article-evaluator] Section ${sectionKey} has ${parts.length} parts`);

            parts.forEach((part, partIndex) => {
                const titleInput = part.querySelector('.part-title');
                const textArea = part.querySelector('.part-text');
                
                const partData = {
                    title: titleInput?.value?.trim() || '',
                    text: textArea?.value?.trim() || ''
                };
                
                console.log(`[article-evaluator] ${sectionKey}[${partIndex}]:`, {
                    title: partData.title,
                    textLength: partData.text?.length || 0
                });
                
                sectionData.parts.push(partData);
            });

            payload[sectionKey] = sectionData;
        });
        
        // إضافة word_counts كـ object منفصل
        payload.word_counts = wordCounts;
        console.log('[article-evaluator] Word counts:', wordCounts);

        // إضافة console.log للتصحيح
        console.log('[article-evaluator] Final collected payload:', payload);

        return payload;
    },

    /**
     * تعبئة الحقول من payload (للتعديل)
     */
    populateFields(payload) {
        const container = this._container;
        if (!container) {
            console.error('[article-evaluator] Container not found - buildUI was not called');
            return;
        }

        console.log('[article-evaluator] Populating fields from payload:', payload);

        // تعبئة الحقول الثابتة
        Object.keys(payload).forEach(key => {
            if (key === 'introduction' || key === 'body' || key === 'conclusion' || 
                key === 'word_counts' || key === 'formatting_compliance') {
                return; // سيتم التعامل معها لاحقاً
            }

            const input = container.querySelector(`[name="${key}"]`);
            if (input) {
                input.value = payload[key] || '';
                // إطلاق حدث input لتحديث أي listeners
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });

        // تعبئة formatting_compliance (chips)
        if (payload.formatting_compliance && typeof payload.formatting_compliance === 'object') {
            const chipsContainer = container.querySelector('.ai-chips[data-chips-key="formatting_compliance"]');
            if (chipsContainer) {
                // إزالة active من جميع chips أولاً
                chipsContainer.querySelectorAll('.ai-chip').forEach(chip => {
                    chip.classList.remove('active');
                });
                
                // إضافة active للـ chips المحددة
                Object.keys(payload.formatting_compliance).forEach(chipValue => {
                    const chip = chipsContainer.querySelector(`.ai-chip[data-value="${chipValue}"]`);
                    if (chip && payload.formatting_compliance[chipValue] === 1) {
                        chip.classList.add('active');
                    }
                });
            }
        }

        // تعبئة الأقسام الديناميكية (introduction, body, conclusion)
        ['introduction', 'body', 'conclusion'].forEach(sectionKey => {
            if (!payload[sectionKey] || !payload[sectionKey].parts) {
                return;
            }

            const section = container.querySelector(`.article-section[data-section-key="${sectionKey}"]`);
            if (!section) {
                console.warn(`[article-evaluator] Section ${sectionKey} not found`);
                return;
            }

            const partsContainer = section.querySelector('.section-parts');
            if (!partsContainer) {
                console.warn(`[article-evaluator] Parts container not found for ${sectionKey}`);
                return;
            }

            // حذف الأجزاء الموجودة
            partsContainer.innerHTML = '';

            // إضافة الأجزاء من payload
            payload[sectionKey].parts.forEach((part, index) => {
                const partHTML = this.buildPartHTML(sectionKey, index + 1);
                partsContainer.insertAdjacentHTML('beforeend', partHTML);
                
                const partEl = partsContainer.lastElementChild;
                if (partEl) {
                    const titleInput = partEl.querySelector('.part-title');
                    const textArea = partEl.querySelector('.part-text');
                    
                    if (titleInput && part.title) {
                        titleInput.value = part.title;
                    }
                    if (textArea && part.text) {
                        textArea.value = part.text;
                        // إطلاق حدث input لتحديث عدد الكلمات
                        textArea.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                }
            });

            // تحديث عنوان القسم إذا كان موجوداً
            const sectionTitle = section.querySelector('.section-title');
            if (sectionTitle && payload[sectionKey].title) {
                sectionTitle.textContent = payload[sectionKey].title;
            }

            // تحديث عدد الكلمات
            this.updateSectionWordCount(container, sectionKey);
        });

        console.log('[article-evaluator] Fields populated successfully');
    },

    /**
     * التحقق من البيانات
     */
    validatePayload(payload) {
        const errors = [];

        // التحقق من الحقول المطلوبة
        console.log('[article-evaluator] Checking required fields:', {
            teacher_name: payload.teacher_name,
            teacher_supervisor: payload.teacher_supervisor,
            article_title: payload.article_title
        });

        if (!payload.teacher_name || !payload.teacher_name.trim()) {
            errors.push('اسم المعلم/الطالب مطلوب');
        }

        if (!payload.teacher_supervisor || !payload.teacher_supervisor.trim()) {
            errors.push('مدرس المعلمين مطلوب');
        }

        if (!payload.article_title || !payload.article_title.trim()) {
            errors.push('عنوان المقال مطلوب');
        }

        // التحقق من وجود جزء نصي واحد على الأقل
        let hasText = false;
        let textParts = [];
        ['introduction', 'body', 'conclusion'].forEach(key => {
            if (payload[key] && payload[key].parts && Array.isArray(payload[key].parts)) {
                payload[key].parts.forEach((part, index) => {
                    const text = part.text?.trim();
                    if (text && text.length > 0) {
                        hasText = true;
                        textParts.push(`${key}[${index}]: ${text.substring(0, 50)}...`);
                    }
                });
            }
        });

        console.log('[article-evaluator] Validation check:');
        console.log('- Has text:', hasText);
        console.log('- Text parts found:', textParts);
        console.log('- Payload sections:', {
            introduction: payload.introduction,
            body: payload.body,
            conclusion: payload.conclusion
        });

        if (!hasText) {
            errors.push('يجب إضافة نص المقال في أحد الأجزاء على الأقل');
        }

        return {
            valid: errors.length === 0,
            errors: errors
        };
    }
};

