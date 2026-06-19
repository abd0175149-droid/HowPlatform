<!-- مودال موافقة الشروط والأحكام -->
<div id="termsConsentModal" class="terms-consent-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="terms-modal-title">
    <div class="terms-consent-modal__dialog">
        <div class="terms-consent-modal__badge">
            <i class="fas fa-file-contract"></i>
            <span>موافقة مطلوبة</span>
        </div>
        
        <h2 id="terms-modal-title">مرحباً <span data-terms-student>طالب</span> 👋</h2>
        
        <p class="terms-consent-modal__intro">
            للاستمرار في استخدام منصة كيف التعليمية، يرجى قراءة والموافقة على 
            <a href="<?= site_url('auth/terms') ?>" target="_blank">الشروط والأحكام</a> 
            و<a href="<?= site_url('auth/privacy') ?>" target="_blank">سياسة الخصوصية</a>.
        </p>
        
        <ul class="terms-consent-modal__list">
            <li>
                <i class="fas fa-check-circle"></i>
                <span>استخدام المنصة للأغراض التعليمية فقط</span>
            </li>
            <li>
                <i class="fas fa-check-circle"></i>
                <span>احترام حقوق الملكية الفكرية للمحتوى</span>
            </li>
            <li>
                <i class="fas fa-check-circle"></i>
                <span>حماية خصوصية البيانات والالتزام بسياسة الخصوصية</span>
            </li>
            <li>
                <i class="fas fa-check-circle"></i>
                <span>استخدام المنصة بشكل مسؤول وأخلاقي</span>
            </li>
        </ul>
        
        <div class="terms-consent-modal__actions">
            <button 
                type="button" 
                class="terms-consent-modal__btn terms-consent-modal__btn--primary" 
                data-terms-accept
            >
                <i class="fas fa-check"></i>
                <span>أوافق وأتابع</span>
            </button>
            <button 
                type="button" 
                class="terms-consent-modal__btn terms-consent-modal__btn--ghost" 
                data-terms-decline
            >
                <i class="fas fa-times"></i>
                <span>رفض</span>
            </button>
        </div>
        
        <div class="terms-consent-modal__warning">
            <i class="fas fa-exclamation-triangle"></i>
            <span>
                <strong>تنبيه:</strong> في حالة رفض الشروط والأحكام، سيتم حذف حسابك نهائياً ولن تتمكن من الوصول إلى المنصة.
            </span>
        </div>
    </div>
</div>
