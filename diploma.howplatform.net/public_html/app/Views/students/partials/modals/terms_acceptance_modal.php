<!-- مودال موافقة الشروط والأحكام -->
<div id="termsAcceptanceModal" class="terms-modal" style="display: none;">
    <div class="terms-modal-overlay"></div>
    <div class="terms-modal-content">
        <div class="terms-modal-header">
            <h2>
                <i class="fas fa-file-contract"></i>
                الشروط والأحكام
            </h2>
            <p class="terms-subtitle">يرجى قراءة الشروط والأحكام والموافقة عليها للمتابعة</p>
        </div>
        
        <div class="terms-modal-body">
            <div class="terms-content-wrapper">
                <div class="terms-content">
                    <h3>شروط وأحكام استخدام منصة كيف التعليمية</h3>
                    
                    <section>
                        <h4>1. القبول بالشروط</h4>
                        <p>باستخدامك لهذه المنصة التعليمية، فإنك تقر بأنك قد قرأت وفهمت وتوافق على الالتزام بجميع الشروط والأحكام الواردة أدناه.</p>
                    </section>
                    
                    <section>
                        <h4>2. استخدام المنصة</h4>
                        <p>يجب استخدام المنصة للأغراض التعليمية فقط. يُمنع استخدامها لأي غرض غير قانوني أو غير أخلاقي.</p>
                    </section>
                    
                    <section>
                        <h4>3. المحتوى والمواد</h4>
                        <p>جميع المواد والمحتوى الموجود على المنصة محمي بحقوق الملكية الفكرية. يُمنع نسخ أو توزيع أي محتوى دون إذن كتابي.</p>
                    </section>
                    
                    <section>
                        <h4>4. خصوصية البيانات</h4>
                        <p>نلتزم بحماية خصوصيتك. سيتم استخدام بياناتك وفقاً لسياسة الخصوصية الخاصة بنا.</p>
                    </section>
                    
                    <section>
                        <h4>5. المسؤولية</h4>
                        <p>المنصة تقدم خدمات تعليمية "كما هي". لا نتحمل أي مسؤولية عن الأضرار المباشرة أو غير المباشرة الناتجة عن استخدام المنصة.</p>
                    </section>
                    
                    <section>
                        <h4>6. إلغاء الحساب</h4>
                        <p>في حالة عدم الموافقة على الشروط والأحكام، سيتم حذف حسابك تلقائياً وعدم إمكانية الوصول إلى المنصة.</p>
                    </section>
                    
                    <section>
                        <h4>7. التعديلات</h4>
                        <p>نحتفظ بالحق في تعديل هذه الشروط والأحكام في أي وقت. سيتم إشعارك بأي تغييرات جوهرية.</p>
                    </section>
                    
                    <div class="terms-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>تنبيه مهم:</strong> في حالة عدم الموافقة على الشروط والأحكام، سيتم حذف حسابك نهائياً ولن تتمكن من الوصول إلى المنصة.
                    </div>
                </div>
            </div>
            
            <div class="terms-checkbox-wrapper">
                <label class="terms-checkbox-label">
                    <input type="checkbox" id="termsCheckbox" class="terms-checkbox">
                    <span class="terms-checkbox-custom"></span>
                    <span class="terms-checkbox-text">
                        أقر بأنني قد قرأت وفهمت جميع الشروط والأحكام أعلاه وأوافق عليها
                    </span>
                </label>
            </div>
        </div>
        
        <div class="terms-modal-footer">
            <button type="button" class="terms-btn terms-btn-reject" id="rejectTermsBtn">
                <i class="fas fa-times"></i>
                رفض
            </button>
            <button type="button" class="terms-btn terms-btn-accept" id="acceptTermsBtn" disabled>
                <i class="fas fa-check"></i>
                موافقة
            </button>
        </div>
    </div>
</div>

<style>
    /* مودال الشروط والأحكام */
    .terms-modal {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
        animation: fadeIn 0.3s ease-out;
    }
    
    .terms-modal-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.75);
        backdrop-filter: blur(3.2px);
    }
    
    .terms-modal-content {
        position: relative;
        width: 100%;
        max-width: 560px;
        max-height: 90vh;
        background: var(--surface-0, #ffffff);
        border-radius: 16px;
        box-shadow: 0 16px 48px rgba(0, 0, 0, 0.3);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: slideUp 0.3s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideUp {
        from { transform: translateY(24px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    .terms-modal-header {
        padding: 19.2px 22.4px;
        border-bottom: 1.6px solid var(--border-color, #e5e7eb);
        background: linear-gradient(135deg, var(--btn-primary-bg, #2563eb) 0%, var(--accent-500, #3b82f6) 100%);
        color: white;
        text-align: right;
    }
    
    .terms-modal-header h2 {
        margin: 0;
        font-size: 1.75rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 9.6px;
    }
    
    .terms-modal-header h2 i {
        font-size: 1.5rem;
    }
    
    .terms-subtitle {
        margin: 6.4px 0 0;
        font-size: 0.95rem;
        opacity: 0.95;
        font-weight: 400;
    }
    
    .terms-modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 19.2px 22.4px;
    }
    
    .terms-content-wrapper {
        margin-bottom: 19.2px;
    }
    
    .terms-content {
        font-size: 0.95rem;
        line-height: 1.8;
        color: var(--text-strong, #1f2937);
    }
    
    .terms-content h3 {
        color: var(--btn-primary-bg, #2563eb);
        font-size: 1.4rem;
        margin: 0 0 16px;
        padding-bottom: 9.6px;
        border-bottom: 1.6px solid var(--border-color, #e5e7eb);
    }
    
    .terms-content section {
        margin-bottom: 19.2px;
    }
    
    .terms-content section h4 {
        color: var(--accent-600, #2563eb);
        font-size: 1.1rem;
        margin: 0 0 8px;
        font-weight: 700;
    }
    
    .terms-content section p {
        margin: 0;
        color: var(--text-strong, #374151);
        text-align: right;
    }
    
    .terms-warning {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border: 1.6px solid #f59e0b;
        border-radius: 9.6px;
        padding: 12.8px;
        margin-top: 19.2px;
        display: flex;
        align-items: flex-start;
        gap: 9.6px;
        direction: rtl;
    }
    
    .terms-warning i {
        color: #f59e0b;
        font-size: 1.5rem;
        flex-shrink: 0;
        margin-top: 1.6px;
    }
    
    .terms-warning strong {
        color: #92400e;
        display: block;
        margin-bottom: 3.2px;
    }
    
    .terms-checkbox-wrapper {
        margin-top: 19.2px;
        padding-top: 16px;
        border-top: 1.6px solid var(--border-color, #e5e7eb);
    }
    
    .terms-checkbox-label {
        display: flex;
        align-items: flex-start;
        gap: 9.6px;
        cursor: pointer;
        user-select: none;
        direction: rtl;
    }
    
    .terms-checkbox {
        display: none;
    }
    
    .terms-checkbox-custom {
        width: 19.2px;
        height: 19.2px;
        border: 1.6px solid var(--border-color, #d1d5db);
        border-radius: 4.8px;
        background: var(--surface-0, #ffffff);
        flex-shrink: 0;
        margin-top: 1.6px;
        transition: all 0.2s ease;
        position: relative;
    }
    
    .terms-checkbox:checked + .terms-checkbox-custom {
        background: var(--btn-primary-bg, #2563eb);
        border-color: var(--btn-primary-bg, #2563eb);
    }
    
    .terms-checkbox:checked + .terms-checkbox-custom::after {
        content: '✓';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: white;
        font-size: 11.2px;
        font-weight: bold;
    }
    
    .terms-checkbox-text {
        flex: 1;
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-strong, #1f2937);
        line-height: 1.5;
    }
    
    .terms-modal-footer {
        padding: 16px 22.4px;
        border-top: 1.6px solid var(--border-color, #e5e7eb);
        background: var(--surface-1, #f9fafb);
        display: flex;
        gap: 9.6px;
        justify-content: flex-start;
    }
    
    .terms-btn {
        padding: 9.6px 19.2px;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6.4px;
    }
    
    .terms-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .terms-btn-reject {
        background: var(--error-500, #ef4444);
        color: white;
    }
    
    .terms-btn-reject:hover:not(:disabled) {
        background: var(--error-600, #dc2626);
        transform: translateY(-1.6px);
        box-shadow: 0 3.2px 9.6px rgba(239, 68, 68, 0.3);
    }
    
    .terms-btn-accept {
        background: linear-gradient(135deg, var(--btn-primary-bg, #2563eb) 0%, var(--accent-500, #3b82f6) 100%);
        color: white;
    }
    
    .terms-btn-accept:hover:not(:disabled) {
        transform: translateY(-1.6px);
        box-shadow: 0 3.2px 9.6px rgba(37, 99, 235, 0.4);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .terms-modal-content {
            max-width: 95vw;
            max-height: 95vh;
        }
        
        .terms-modal-header {
            padding: 16px;
        }
        
        .terms-modal-header h2 {
            font-size: 1.4rem;
        }
        
        .terms-modal-body {
            padding: 16px;
        }
        
        .terms-content h3 {
            font-size: 1.2rem;
        }
        
        .terms-modal-footer {
            flex-direction: column-reverse;
            padding: 12.8px 16px;
        }
        
        .terms-btn {
            width: 100%;
            justify-content: center;
        }
    }
    
    /* منع التمرير في الخلفية */
    body.terms-modal-open {
        overflow: hidden !important;
    }
</style>

