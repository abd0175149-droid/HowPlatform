/**
 * JavaScript للبانر في واجهة الطالب عند الاستعراض كأدمن
 */

(function() {
  'use strict';

  // الحصول على API_BASE من window.API_BASE أو site_url
  // انتظار حتى يكون window.API_BASE متاحاً
  function getApiBase() {
    if (window.API_BASE) return window.API_BASE.replace(/\/+$/, '');
    if (window.__APP_CONFIG__?.API_BASE) return window.__APP_CONFIG__.API_BASE.replace(/\/+$/, '');
    // fallback: استخدام المسار الحالي
    return window.location.origin;
  }
  
  const API_BASE = getApiBase();
  const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const CSRF_HEADER = document.querySelector('meta[name="csrf-header"]')?.content || 'X-CSRF-TOKEN';
  
  // استخدام console.info بدلاً من console.log لأن console.log معطل في واجهة الطالب
  if (typeof console !== 'undefined' && console.info) {
    console.info('Admin view script loaded. API_BASE:', API_BASE);
  }

  /**
   * إنهاء الاستعراض
   */
  async function stopView() {
    const confirmResult = await Swal.fire({
      title: 'إنهاء الاستعراض',
      text: 'هل أنت متأكد من إنهاء الاستعراض والعودة لواجهة الأدمن؟',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'نعم، إنهاء',
      cancelButtonText: 'إلغاء'
    });

    if (!confirmResult.isConfirmed) {
      return;
    }

    try {
      const response = await fetch(`${API_BASE}/admins/api/student-view/stop`, {
        method: 'POST',
        headers: {
          [CSRF_HEADER]: CSRF_TOKEN,
        },
      });

      const result = await response.json();
      if (result.success) {
        window.location.href = result.redirect || `${API_BASE}/admins/`;
      } else {
        alert(result.message || 'حدث خطأ');
      }
    } catch (error) {
      console.error('Error stopping view:', error);
      alert('حدث خطأ أثناء إنهاء الاستعراض');
    }
  }

  /**
   * فتح modal/صفحة تعديل الإعدادات
   */
  function editSettings() {
    // الانتقال لواجهة الأدمن مع فتح تبويب استعراض واجهة الطالب
    const adminUrl = `${API_BASE}/admins/`;
    // حفظ في sessionStorage للانتقال للتبويب بعد التحميل
    try {
      sessionStorage.setItem('admin_open_tab', 'student-view');
    } catch (e) {
      console.warn('Could not save tab preference:', e);
    }
    window.location.href = adminUrl;
  }

  /**
   * تبديل حالة البانر (فتح/إغلاق)
   */
  function toggleBanner() {
    const banner = document.getElementById('admin-view-banner');
    const toggleBtn = document.getElementById('toggle-banner-btn');
    const toggleIcon = document.getElementById('toggle-banner-icon');
    
    if (!banner) {
      warn('Banner not found');
      return;
    }

    const isCollapsed = banner.classList.contains('collapsed');
    log('Toggling banner. Current state:', isCollapsed ? 'collapsed' : 'expanded');
    
    if (isCollapsed) {
      banner.classList.remove('collapsed');
      if (toggleIcon) {
        toggleIcon.classList.remove('fa-chevron-down');
        toggleIcon.classList.add('fa-chevron-up');
      }
      // حفظ الحالة في localStorage
      try {
        localStorage.setItem('admin_view_banner_collapsed', 'false');
      } catch (e) {
        warn('Could not save banner state:', e);
      }
      log('Banner expanded');
    } else {
      banner.classList.add('collapsed');
      if (toggleIcon) {
        toggleIcon.classList.remove('fa-chevron-up');
        toggleIcon.classList.add('fa-chevron-down');
      }
      // حفظ الحالة في localStorage
      try {
        localStorage.setItem('admin_view_banner_collapsed', 'true');
      } catch (e) {
        warn('Could not save banner state:', e);
      }
      log('Banner collapsed');
    }
  }

  /**
   * استعادة حالة البانر من localStorage
   */
  function restoreBannerState() {
    const banner = document.getElementById('admin-view-banner');
    if (!banner) return;

    const savedState = localStorage.getItem('admin_view_banner_collapsed');
    if (savedState === 'true') {
      banner.classList.add('collapsed');
      const toggleIcon = document.getElementById('toggle-banner-icon');
      if (toggleIcon) {
        toggleIcon.classList.remove('fa-chevron-up');
        toggleIcon.classList.add('fa-chevron-down');
      }
    }
  }

  // دوال مساعدة للـ logging (لأن console.log معطل في واجهة الطالب)
  const log = typeof console !== 'undefined' && console.info ? console.info : (() => {});
  const warn = typeof console !== 'undefined' && console.warn ? console.warn : (() => {});

  /**
   * تهيئة
   */
  function init() {
    log('Initializing admin view banner...');
    
    const stopBtn = document.getElementById('stop-view-btn');
    const editBtn = document.getElementById('edit-settings-btn');
    const toggleBtn = document.getElementById('toggle-banner-btn');
    const bannerHeader = document.querySelector('.banner-header');
    const banner = document.getElementById('admin-view-banner');

    log('Elements found:', {
      stopBtn: !!stopBtn,
      editBtn: !!editBtn,
      toggleBtn: !!toggleBtn,
      bannerHeader: !!bannerHeader,
      banner: !!banner
    });

    if (stopBtn) {
      stopBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        log('Stop button clicked');
        stopView();
      });
    } else {
      warn('Stop button not found');
    }

    if (editBtn) {
      editBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        log('Edit button clicked');
        editSettings();
      });
    } else {
      warn('Edit button not found');
    }

    // زر التبديل
    if (toggleBtn) {
      toggleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        log('Toggle button clicked');
        toggleBanner();
      });
    } else {
      warn('Toggle button not found');
    }

    // النقر على الهيدر لتبديل البانر
    if (bannerHeader) {
      bannerHeader.addEventListener('click', function(e) {
        // تجنب التبديل عند النقر على الأزرار
        if (!e.target.closest('button')) {
          log('Banner header clicked');
          toggleBanner();
        }
      });
    }

    // استعادة الحالة المحفوظة
    restoreBannerState();
    
    log('Admin view banner initialized');
  }

  // تهيئة عند تحميل الصفحة
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

