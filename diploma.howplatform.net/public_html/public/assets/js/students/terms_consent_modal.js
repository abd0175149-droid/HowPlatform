const TermsConsentModal = (() => {
  const state = {
    modal: null,
    acceptBtn: null,
    declineBtn: null,
    studentNameEl: null,
    isVisible: false,
    isLoading: false,
  };

  const endpoints = {
    status: '/students/terms/status',
    accept: '/students/terms/accept',
    decline: '/students/terms/decline',
  };

  function qs(selector) {
    return document.querySelector(selector);
  }

  function getCsrf() {
    const tokenEl = qs('meta[name="csrf-token"]');
    const headerEl = qs('meta[name="csrf-header"]');
    return {
      token: tokenEl?.getAttribute('content') || '',
      header: headerEl?.getAttribute('content') || 'X-CSRF-TOKEN',
    };
  }

  function updateCsrfFromResponse(res) {
    const newToken =
      res.headers.get('X-CSRF-TOKEN') ||
      res.headers.get('X-Csrf-Token') ||
      res.headers.get('X-Csrf');
    if (newToken) {
      const tokenEl = qs('meta[name="csrf-token"]');
      if (tokenEl) tokenEl.setAttribute('content', newToken);
    }
  }

  async function request(method, url, body) {
    const { token, header } = getCsrf();
    const headers = {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    };
    if (method !== 'GET') {
      headers['Content-Type'] = 'application/json';
      if (token) headers[header] = token;
    }

    const res = await fetch(url, {
      method,
      credentials: 'same-origin',
      cache: 'no-store',
      headers,
      body: body ? JSON.stringify(body) : undefined,
    });

    updateCsrfFromResponse(res);

    const data = await res
      .json()
      .catch(() => ({ status: 'error', message: 'تعذّر قراءة استجابة الخادم.' }));

    if (!res.ok || data.status === 'error') {
      throw new Error(data.message || 'حدث خطأ غير متوقع.');
    }

    return data;
  }

  function setLoading(isLoading) {
    state.isLoading = isLoading;
    if (state.acceptBtn) state.acceptBtn.disabled = isLoading;
    if (state.declineBtn) state.declineBtn.disabled = isLoading;
    if (state.modal) state.modal.classList.toggle('is-loading', isLoading);
  }

  function showModal() {
    if (!state.modal) {
      console.error('[TermsConsent] Modal element not found');
      return;
    }
    console.log('[TermsConsent] Showing modal');
    state.isVisible = true;
    
    // التأكد من أن المودال في أعلى الصفحة
    state.modal.style.display = 'flex';
    state.modal.style.zIndex = '99999';
    state.modal.style.position = 'fixed';
    state.modal.style.top = '0';
    state.modal.style.left = '0';
    state.modal.style.width = '100%';
    state.modal.style.height = '100%';
    
    // التأكد من ظهور dialog
    const dialog = state.modal.querySelector('.terms-consent-modal__dialog');
    if (dialog) {
      dialog.style.visibility = 'visible';
      dialog.style.opacity = '1';
      dialog.style.zIndex = '100000';
    }
    
    setTimeout(() => {
      state.modal.classList.add('show');
    }, 10);
    state.modal.removeAttribute('aria-hidden');
    document.body.classList.add('terms-modal-open');
    // منع التمرير
    document.documentElement.style.overflow = 'hidden';
    
    console.log('[TermsConsent] Modal shown, dialog visible:', dialog ? 'yes' : 'no');
  }

  function hideModal() {
    if (!state.modal) return;
    state.isVisible = false;
    state.modal.classList.remove('show');
    // إخفاء بعد انتهاء الانتقال
    setTimeout(() => {
      state.modal.style.display = 'none';
    }, 300);
    state.modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('terms-modal-open');
    // إعادة التمرير
    document.documentElement.style.overflow = '';
  }

  async function fetchStatus() {
    try {
      console.log('[TermsConsent] Checking status...');
      const res = await request('GET', endpoints.status);
      console.log('[TermsConsent] Status response:', res);
      
      if (!res?.data) {
        console.warn('[TermsConsent] No data in response');
        return;
      }
      
      if (res.data.user_name && state.studentNameEl) {
        state.studentNameEl.textContent = res.data.user_name;
      }
      
      if (!res.data.consented) {
        // المستخدم لم يوافق بعد - عرض المودال
        console.log('[TermsConsent] User has not consented, showing modal');
        if (!state.isVisible) {
          showModal();
        }
      } else {
        // المستخدم وافق - إخفاء المودال
        console.log('[TermsConsent] User has consented, hiding modal');
        if (state.isVisible) {
          hideModal();
        }
      }
    } catch (err) {
      console.error('[TermsConsent] status error:', err);
      // في حالة الخطأ، لا نعرض المودال
      hideModal();
    }
  }

  async function acceptTerms() {
    try {
      console.log('[TermsConsent] Accepting terms...');
      setLoading(true);
      const res = await request('POST', endpoints.accept);
      console.log('[TermsConsent] Accept response:', res);
      
      // إخفاء المودال فوراً
      hideModal();
      
      const swal = window.Swal;
      if (swal?.fire) {
        swal.fire({
          icon: 'success',
          title: 'تم الحفظ',
          text: res.message || 'تم تسجيل موافقتك بنجاح.',
          confirmButtonText: 'متابعة',
          timer: 2000,
          showConfirmButton: true,
        }).then(() => {
          // إعادة فحص الحالة للتأكد
          fetchStatus();
        });
      } else {
        alert(res.message || 'تم تسجيل موافقتك بنجاح.');
        // إعادة فحص الحالة
        setTimeout(() => {
          fetchStatus();
        }, 500);
      }
    } catch (err) {
      console.error('[TermsConsent] Accept error:', err);
      setLoading(false);
      const swal = window.Swal;
      if (swal?.fire) {
        swal.fire({
          icon: 'error',
          title: 'خطأ',
          text: err.message || 'تعذّر حفظ الموافقة.',
        });
      } else {
        alert(err.message || 'تعذّر حفظ الموافقة.');
      }
    }
  }

  async function declineTerms() {
    const swal = window.Swal;
    let confirmed = true;
    if (swal?.fire) {
      const result = await swal.fire({
        icon: 'warning',
        title: 'هل أنت متأكد؟',
        text: 'سيتم حذف حسابك نهائياً ولن تتمكن من استعادته.',
        confirmButtonText: 'نعم، احذف الحساب',
        cancelButtonText: 'الرجوع',
        reverseButtons: true,
        showCancelButton: true,
      });
      confirmed = result.isConfirmed;
    } else {
      confirmed = window.confirm('سيتم حذف حسابك نهائياً. هل أنت متأكد؟');
    }
    if (!confirmed) return;

    try {
      setLoading(true);
      const res = await request('POST', endpoints.decline);
      if (swal?.fire) {
        await swal.fire({
          icon: 'success',
          title: 'تم حذف الحساب',
          text: res.message || 'تم تنفيذ طلبك.',
          confirmButtonText: 'متابعة',
        });
      }
      window.location.href = res.redirect || 'https://auth.howplatform.net/login';
    } catch (err) {
      if (swal?.fire) {
        swal.fire({
          icon: 'error',
          title: 'تعذّر الحذف',
          text: err.message || 'حاول لاحقاً.',
        });
      } else {
        alert(err.message || 'تعذّر حذف الحساب.');
      }
    } finally {
      setLoading(false);
    }
  }

  function bindEvents() {
    if (state.acceptBtn) state.acceptBtn.addEventListener('click', acceptTerms);
    if (state.declineBtn) state.declineBtn.addEventListener('click', declineTerms);
    
    // منع إغلاق المودال بالنقر على الخلفية
    if (state.modal) {
      state.modal.addEventListener('click', function(e) {
        if (e.target === state.modal) {
          // لا نفعل شيئاً - منع الإغلاق
          e.preventDefault();
          e.stopPropagation();
        }
      });
      
      // منع إغلاق المودال بـ ESC
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && state.isVisible) {
          e.preventDefault();
          e.stopPropagation();
          // لا نسمح بالإغلاق
        }
      }, true);
    }
  }

  function cacheDom() {
    state.modal        = qs('#termsConsentModal');
    state.acceptBtn    = state.modal?.querySelector('[data-terms-accept]') || null;
    state.declineBtn   = state.modal?.querySelector('[data-terms-decline]') || null;
    state.studentNameEl= state.modal?.querySelector('[data-terms-student]') || null;
  }

  function init() {
    cacheDom();
    if (!state.modal) {
      console.warn('[TermsConsent] Modal not found in DOM');
      return;
    }
    console.log('[TermsConsent] Initialized, modal found:', state.modal);
    bindEvents();
    // تأخير بسيط للتأكد من تحميل الصفحة
    setTimeout(() => {
      fetchStatus();
    }, 300);
  }

  return { init };
})();

// التهيئة التلقائية
(function autoInit() {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      setTimeout(() => TermsConsentModal.init(), 100);
    });
  } else {
    setTimeout(() => TermsConsentModal.init(), 100);
  }
})();

// تصدير للاستخدام الخارجي
if (typeof module !== 'undefined' && module.exports) {
  module.exports = TermsConsentModal;
}
if (typeof window !== 'undefined') {
  window.TermsConsentModal = TermsConsentModal;
}

