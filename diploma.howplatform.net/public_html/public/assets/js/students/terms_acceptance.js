/**
 * نظام موافقة الشروط والأحكام
 * يظهر مودال للطلاب الذين لم يوافقوا بعد على الشروط
 */

(function() {
    'use strict';
    
    if (window.__TERMS_ACCEPTANCE_LOADED__) return;
    window.__TERMS_ACCEPTANCE_LOADED__ = true;
    
    const API_BASE = window.API_BASE || '';
    const MODAL_ID = 'termsAcceptanceModal';
    const CHECKBOX_ID = 'termsCheckbox';
    const ACCEPT_BTN_ID = 'acceptTermsBtn';
    const REJECT_BTN_ID = 'rejectTermsBtn';
    
    let modal = null;
    let checkbox = null;
    let acceptBtn = null;
    let rejectBtn = null;
    let isLoading = false;
    
    /**
     * التحقق من حالة الموافقة من السيرفر
     */
    async function checkTermsAcceptance() {
        try {
            const response = await fetch(`${API_BASE}/students/terms/check`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            
            if (data.success && data.terms_accepted === false) {
                // لم يوافق بعد - عرض المودال
                showModal();
            }
        } catch (error) {
            console.error('Error checking terms acceptance:', error);
            // في حالة الخطأ، لا نعرض المودال لتجنب حجب الوصول
        }
    }
    
    /**
     * عرض المودال
     */
    function showModal() {
        if (!modal) {
            modal = document.getElementById(MODAL_ID);
            checkbox = document.getElementById(CHECKBOX_ID);
            acceptBtn = document.getElementById(ACCEPT_BTN_ID);
            rejectBtn = document.getElementById(REJECT_BTN_ID);
            
            if (!modal) {
                console.error('Terms modal not found');
                return;
            }
        }
        
        // منع التمرير في الخلفية
        document.body.classList.add('terms-modal-open');
        
        // عرض المودال
        modal.style.display = 'flex';
        
        // ربط الأحداث
        bindEvents();
        
        // التركيز على checkbox
        if (checkbox) {
            setTimeout(() => checkbox.focus(), 100);
        }
    }
    
    /**
     * إخفاء المودال
     */
    function hideModal() {
        if (modal) {
            modal.style.display = 'none';
            document.body.classList.remove('terms-modal-open');
        }
    }
    
    /**
     * ربط الأحداث
     */
    function bindEvents() {
        if (!checkbox || !acceptBtn || !rejectBtn) return;
        
        // تغيير حالة زر الموافقة عند تغيير checkbox
        checkbox.addEventListener('change', function() {
            acceptBtn.disabled = !this.checked;
        });
        
        // زر الموافقة
        acceptBtn.addEventListener('click', handleAccept);
        
        // زر الرفض
        rejectBtn.addEventListener('click', handleReject);
        
        // إغلاق عند النقر على الخلفية (مع التحقق)
        const overlay = modal?.querySelector('.terms-modal-overlay');
        if (overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    // لا نسمح بالإغلاق بالنقر على الخلفية - يجب اختيار
                    return;
                }
            });
        }
        
        // منع الإغلاق بـ ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal && modal.style.display !== 'none') {
                e.preventDefault();
                // لا نسمح بالإغلاق بـ ESC - يجب اختيار
            }
        });
    }
    
    /**
     * معالجة الموافقة
     */
    async function handleAccept() {
        if (isLoading || !checkbox?.checked) return;
        
        isLoading = true;
        acceptBtn.disabled = true;
        rejectBtn.disabled = true;
        
        // تحديث نص الزر
        const originalText = acceptBtn.innerHTML;
        acceptBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري المعالجة...';
        
        try {
            const response = await fetch(`${API_BASE}/students/terms/accept`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    accepted: true
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // نجح - إخفاء المودال وإعادة تحميل الصفحة
                hideModal();
                window.location.reload();
            } else {
                // فشل - عرض رسالة خطأ
                alert(data.message || 'حدث خطأ أثناء حفظ الموافقة. يرجى المحاولة مرة أخرى.');
                acceptBtn.innerHTML = originalText;
                acceptBtn.disabled = false;
                rejectBtn.disabled = false;
            }
        } catch (error) {
            console.error('Error accepting terms:', error);
            alert('حدث خطأ في الاتصال. يرجى المحاولة مرة أخرى.');
            acceptBtn.innerHTML = originalText;
            acceptBtn.disabled = false;
            rejectBtn.disabled = false;
        } finally {
            isLoading = false;
        }
    }
    
    /**
     * معالجة الرفض
     */
    async function handleReject() {
        if (isLoading) return;
        
        // تأكيد قبل الحذف
        const result = await Swal.fire({
            title: 'تحذير مهم',
            html: 'في حالة رفض الشروط والأحكام، سيتم حذف حسابك نهائياً ولن تتمكن من الوصول إلى المنصة.<br><br>هل أنت متأكد من رغبتك في رفض الشروط وحذف حسابك؟',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'نعم، أرفض وأحذف حسابي',
            cancelButtonText: 'تراجع',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6'
        });
        
        if (!result.isConfirmed) {
            return;
        }
        
        isLoading = true;
        acceptBtn.disabled = true;
        rejectBtn.disabled = true;
        
        // تحديث نص الزر
        const originalText = rejectBtn.innerHTML;
        rejectBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري حذف الحساب...';
        
        try {
            const response = await fetch(`${API_BASE}/students/terms/reject`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    rejected: true
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // نجح - إعادة التوجيه إلى صفحة تسجيل الخروج
                alert('تم حذف حسابك بنجاح. شكراً لك.');
                window.location.href = data.redirect_url || `${API_BASE}/students/logout`;
            } else {
                // فشل - عرض رسالة خطأ
                alert(data.message || 'حدث خطأ أثناء حذف الحساب. يرجى المحاولة مرة أخرى.');
                rejectBtn.innerHTML = originalText;
                acceptBtn.disabled = false;
                rejectBtn.disabled = false;
            }
        } catch (error) {
            console.error('Error rejecting terms:', error);
            alert('حدث خطأ في الاتصال. يرجى المحاولة مرة أخرى.');
            rejectBtn.innerHTML = originalText;
            acceptBtn.disabled = false;
            rejectBtn.disabled = false;
        } finally {
            isLoading = false;
        }
    }
    
    /**
     * التهيئة
     */
    function init() {
        // التحقق من حالة الموافقة عند تحميل الصفحة
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', checkTermsAcceptance);
        } else {
            // الصفحة محملة بالفعل
            setTimeout(checkTermsAcceptance, 500);
        }
    }
    
    // بدء التهيئة
    init();
    
    // تصدير للاستخدام الخارجي إن لزم
    window.TermsAcceptance = {
        check: checkTermsAcceptance,
        show: showModal,
        hide: hideModal
    };
})();

