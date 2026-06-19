/**
 * أدوات إدارة المودالات الموحدة
 * - إضافة/إزالة blur على الخلفية
 * - منع scroll للخلفية
 * - إدارة z-index
 */

(function() {
    'use strict';

    // فتح مودال
    function openModal(modalElement) {
        if (!modalElement) return;
        
        // إضافة class active
        modalElement.classList.add('active');
        modalElement.setAttribute('aria-hidden', 'false');
        
        // منع scroll للخلفية
        document.body.classList.add('modal-open');
        
        // التركيز على المودال (للإمكانية)
        const focusableElement = modalElement.querySelector('input, textarea, select, button, [tabindex]:not([tabindex="-1"])');
        if (focusableElement) {
            setTimeout(() => focusableElement.focus(), 100);
        }
        
        // إغلاق عند الضغط على Escape
        const handleEscape = (e) => {
            if (e.key === 'Escape' && modalElement.classList.contains('active')) {
                closeModal(modalElement);
            }
        };
        document.addEventListener('keydown', handleEscape);
        modalElement._escapeHandler = handleEscape;
    }

    // إغلاق مودال
    function closeModal(modalElement) {
        if (!modalElement) return;
        
        // إزالة class active
        modalElement.classList.remove('active');
        modalElement.setAttribute('aria-hidden', 'true');
        
        // إعادة scroll للخلفية
        const hasActiveModals = document.querySelectorAll('.modal-overlay.active, [data-svc-modal].show').length > 0;
        if (!hasActiveModals) {
            document.body.classList.remove('modal-open');
        }
        
        // إزالة event listener للـ Escape
        if (modalElement._escapeHandler) {
            document.removeEventListener('keydown', modalElement._escapeHandler);
            delete modalElement._escapeHandler;
        }
    }

    // إغلاق عند النقر على الخلفية
    function initModalBackdropClose(modalElement) {
        if (!modalElement) return;
        
        modalElement.addEventListener('click', (e) => {
            // إذا كان النقر على الخلفية نفسها (وليس على المحتوى)
            if (e.target === modalElement) {
                closeModal(modalElement);
            }
        });
    }

    // تهيئة جميع المودالات
    function initModals() {
        // تهيئة .modal-overlay
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.setAttribute('aria-hidden', 'true');
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            initModalBackdropClose(modal);
        });

        // تهيئة [data-svc-modal]
        document.querySelectorAll('[data-svc-modal]').forEach(modal => {
            modal.setAttribute('aria-hidden', 'true');
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            initModalBackdropClose(modal);
        });
    }

    // جعل الدوال متاحة عالمياً
    window.openModal = openModal;
    window.closeModal = closeModal;
    window.initModals = initModals;

    // تهيئة عند تحميل الصفحة
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModals);
    } else {
        initModals();
    }
})();
