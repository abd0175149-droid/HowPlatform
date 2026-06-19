/* =========================================
 * نظام Toast موحد - أعلى يسار الشاشة
 * ========================================= */

// إنشاء container للـ toast إذا لم يكن موجوداً
function getToastContainer() {
    let container = document.getElementById('admin-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'admin-toast-container';
        container.className = 'admin-toast-container';
        document.body.appendChild(container);
    }
    return container;
}

// إظهار toast موحد
function showToast(type, message, duration = 4000) {
    const container = getToastContainer();
    
    const icons = {
        success: '<i class="fas fa-check-circle"></i>',
        error: '<i class="fas fa-exclamation-circle"></i>',
        warning: '<i class="fas fa-exclamation-triangle"></i>',
        info: '<i class="fas fa-info-circle"></i>'
    };
    
    const toast = document.createElement('div');
    toast.className = `admin-toast admin-toast-${type}`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <span class="admin-toast-icon">${icons[type] || icons.info}</span>
        <span class="admin-toast-message">${message}</span>
        <button class="admin-toast-close" aria-label="إغلاق" type="button">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(toast);
    
    // إظهار toast بانتقال سلس
    requestAnimationFrame(() => {
        toast.classList.add('show');
    });
    
    // إغلاق toast
    const closeToast = () => {
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    };
    
    // إغلاق عند النقر على زر الإغلاق
    const closeBtn = toast.querySelector('.admin-toast-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeToast);
    }
    
    // إغلاق تلقائي
    if (duration > 0) {
        setTimeout(closeToast, duration);
    }
    
    return toast;
}

// دوال التنبيهات الموحدة (استخدام toast)
function showSuccess(message, title = 'تم', autoClose = true) {
    if (title && title !== 'تم') {
        showToast('success', `${title}: ${message}`, autoClose ? 4000 : 0);
    } else {
        showToast('success', message, autoClose ? 4000 : 0);
    }
}

function showWarning(message, title = 'تنبيه') {
    if (title && title !== 'تنبيه') {
        showToast('warning', `${title}: ${message}`, 5000);
    } else {
        showToast('warning', message, 5000);
    }
}

function showError(message, title = 'خطأ') {
    if (title && title !== 'خطأ') {
        showToast('error', `${title}: ${message}`, 6000);
    } else {
        showToast('error', message, 6000);
    }
}

function showInfo(message, title = 'معلومة') {
    if (title && title !== 'معلومة') {
        showToast('info', `${title}: ${message}`, 4000);
    } else {
        showToast('info', message, 4000);
    }
}

// للحوارات التأكيدية، نستخدم Swal (يحتاج تفاعل المستخدم)
function showConfirm({ title = 'هل أنت متأكد؟', text = '', confirmText = 'موافق', cancelText = 'إلغاء', onConfirm = () => {} }) {
    Swal.fire({
        title,
        text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'var(--primary-500)',
        cancelButtonColor: 'var(--error-500)',
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        customClass: {
            popup: 'admin-swal-popup',
            confirmButton: 'admin-swal-confirm',
            cancelButton: 'admin-swal-cancel'
        }
    }).then((result) => {
        if (result.isConfirmed) onConfirm();
    });
}
