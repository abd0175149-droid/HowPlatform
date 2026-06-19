/**
 * JavaScript لإدارة استعراض واجهة الطالب للأدمن
 */

(function() {
  'use strict';

  // الحصول على API_BASE من window.__APP_CONFIG__ أو window.API_BASE
  const API_BASE = (window.__APP_CONFIG__?.API_BASE || window.API_BASE || '').replace(/\/+$/, '');
  const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const CSRF_HEADER = document.querySelector('meta[name="csrf-header"]')?.content || 'X-CSRF-TOKEN';

  let optionsData = null;

  /**
   * جلب خيارات القوائم المنسدلة
   */
  async function loadOptions() {
    // محاولة استخدام البيانات من PHP أولاً
    if (window.STUDENT_VIEW_DATA) {
      console.log('Using data from PHP');
      console.log('Data keys:', Object.keys(window.STUDENT_VIEW_DATA));
      optionsData = window.STUDENT_VIEW_DATA;
      
      // التأكد من أن البيانات موجودة
      if (!optionsData.diploma_types || !optionsData.universities) {
        console.error('Missing required data in STUDENT_VIEW_DATA');
        // محاولة جلبها من API
      } else {
        // انتظار قصير للتأكد من أن DOM جاهز
        setTimeout(() => {
          setupEventListeners();
        }, 100);
        return;
      }
    }

    // إذا لم تكن متوفرة، جلبها من API
    try {
      const url = `${API_BASE}/admins/api/student-view/options`;
      console.log('Loading options from API:', url);
      
      const response = await fetch(url, {
        headers: {
          [CSRF_HEADER]: CSRF_TOKEN,
        },
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const result = await response.json();
      console.log('Options response:', result);
      
      if (result.success && result.data) {
        optionsData = result.data;
        setTimeout(() => {
          setupEventListeners();
        }, 100);
      } else {
        console.error('Failed to load options:', result);
        showMessage('فشل تحميل البيانات. الرجاء تحديث الصفحة.', 'error');
      }
    } catch (error) {
      console.error('Error loading options:', error);
      showMessage('حدث خطأ أثناء تحميل البيانات: ' + error.message, 'error');
    }
  }

  // متغيرات لتتبع event listeners
  let diplomaChangeHandler = null;
  let universityChangeHandler = null;

  /**
   * إعداد event listeners فقط (بدون إعادة تعبئة القوائم المنسدلة)
   */
  function setupEventListeners() {
    if (!optionsData) {
      console.warn('optionsData is not available');
      console.log('Available data:', window.STUDENT_VIEW_DATA);
      return;
    }

    console.log('Setting up event listeners with optionsData:', optionsData);

    const diplomaSelect = document.getElementById('diploma_type_id');
    const universitySelect = document.getElementById('university_id');
    const specialtySelect = document.getElementById('specialty_id');
    const academicYearSelect = document.getElementById('academic_year_id');

    if (!diplomaSelect || !universitySelect || !academicYearSelect) {
      console.warn('Required select elements not found', {
        diploma: !!diplomaSelect,
        university: !!universitySelect,
        academicYear: !!academicYearSelect
      });
      return;
    }

    // إزالة event listeners القديمة إن وجدت
    if (diplomaChangeHandler) {
      diplomaSelect.removeEventListener('change', diplomaChangeHandler);
    }
    if (universityChangeHandler) {
      universitySelect.removeEventListener('change', universityChangeHandler);
    }

    // عند تغيير نوع الدبلوم
    diplomaChangeHandler = function() {
      const diplomaId = parseInt(this.value);
      console.log('Diploma changed to:', diplomaId);
      
      if (universitySelect) {
        universitySelect.innerHTML = '<option value="">اختر الجامعة</option>';
        
        if (diplomaId && optionsData.university_diploma_types) {
          const allowedUniversities = optionsData.university_diploma_types
            .filter(udt => parseInt(udt.diploma_type_id) === diplomaId)
            .map(udt => parseInt(udt.university_id));
          
          console.log('Allowed universities for diploma', diplomaId, ':', allowedUniversities);
          
          if (optionsData.universities) {
            optionsData.universities
              .filter(u => allowedUniversities.includes(parseInt(u.id)))
              .forEach(u => {
                const option = document.createElement('option');
                option.value = u.id;
                option.textContent = u.name;
                universitySelect.appendChild(option);
              });
          }
        }
      }
      
      // إعادة تعيين التخصص والسنة
      if (specialtySelect) {
        specialtySelect.innerHTML = '<option value="">اختر التخصص (اختياري)</option>';
      }
      if (academicYearSelect) {
        academicYearSelect.innerHTML = '<option value="">اختر السنة الأكاديمية</option>';
      }
    };

    diplomaSelect.addEventListener('change', diplomaChangeHandler);

    // عند تغيير الجامعة
    universityChangeHandler = function() {
      const universityId = parseInt(this.value);
      console.log('University changed to:', universityId);
      
      // تعبئة التخصصات
      if (specialtySelect) {
        specialtySelect.innerHTML = '<option value="">اختر التخصص (اختياري)</option>';
        
        if (universityId && optionsData.university_specialties) {
          const allowedSpecialties = optionsData.university_specialties
            .filter(us => parseInt(us.university_id) === universityId)
            .map(us => parseInt(us.specialty_id));
          
          if (optionsData.specialties) {
            optionsData.specialties
              .filter(s => allowedSpecialties.includes(parseInt(s.id)))
              .forEach(s => {
                const option = document.createElement('option');
                option.value = s.id;
                option.textContent = s.name;
                specialtySelect.appendChild(option);
              });
          }
        }
      }

      // تعبئة السنوات الأكاديمية
      if (academicYearSelect) {
        academicYearSelect.innerHTML = '<option value="">اختر السنة الأكاديمية</option>';
        
        if (universityId && optionsData.university_academic_years) {
          const allowedYears = optionsData.university_academic_years
            .filter(uay => parseInt(uay.university_id) === universityId)
            .map(uay => parseInt(uay.academic_year_id));
          
          if (optionsData.academic_years) {
            optionsData.academic_years
              .filter(ay => allowedYears.includes(parseInt(ay.id)))
              .forEach(ay => {
                const option = document.createElement('option');
                option.value = ay.id;
                option.textContent = ay.name;
                academicYearSelect.appendChild(option);
              });
          }
        }
      }
    };

    universitySelect.addEventListener('change', universityChangeHandler);

    // ── تطبيق الإعدادات المحفوظة ──────────────────────────────────────────
    // الأولوية: server settings (session) > localStorage
    const serverSettings = window.STUDENT_VIEW_SERVER_SETTINGS || null;
    const cachedSettings = loadSettingsFromCache();

    const settingsToApply = serverSettings || cachedSettings;

    if (settingsToApply) {
      applySettingsToForm(settingsToApply);
    } else {
      // إذا لم تكن هناك إعدادات محفوظة، استخدم القيم من PHP إن وجدت
      if (diplomaSelect.value) {
        setTimeout(() => {
          const savedDiplomaVal = diplomaSelect.value;
          const savedUniVal = universitySelect.value;
          diplomaSelect.dispatchEvent(new Event('change', { bubbles: true }));
          // بعد إعادة بناء universitySelect، أعد تحديد قيمته
          setTimeout(() => {
            if (savedUniVal) {
              universitySelect.value = savedUniVal;
              universitySelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
          }, 350);
        }, 300);
      }
    }

    // حفظ الإعدادات في localStorage عند تغيير أي حقل
    const form = document.getElementById('studentViewSettingsForm');
    if (form) {
      form.addEventListener('change', function(e) {
        const formData = new FormData(form);
        const settings = {
          diploma_type_id: formData.get('diploma_type_id') || null,
          university_id: formData.get('university_id') || null,
          specialty_id: formData.get('specialty_id') || null,
          academic_year_id: formData.get('academic_year_id') || null,
        };
        saveSettingsToCache(settings);
      });
    }
  }

  /**
   * حفظ الإعدادات في localStorage
   */
  function saveSettingsToCache(data) {
    try {
      localStorage.setItem('student_view_settings', JSON.stringify(data));
      console.log('Settings saved to cache:', data);
    } catch (error) {
      console.warn('Could not save settings to cache:', error);
    }
  }

  /**
   * جلب الإعدادات من localStorage
   */
  function loadSettingsFromCache() {
    try {
      const cached = localStorage.getItem('student_view_settings');
      if (cached) {
        const data = JSON.parse(cached);
        console.log('Settings loaded from cache:', data);
        return data;
      }
    } catch (error) {
      console.warn('Could not load settings from cache:', error);
    }
    return null;
  }

  /**
   * تطبيق الإعدادات على النموذج
   * نبني القوائم مباشرةً من البيانات بدلاً من cascade من change events
   * هذا يضمن ضبط academic_year_id بشكل صحيح دائماً
   */
  function applySettingsToForm(data) {
    if (!data || !optionsData) {
      console.warn('applySettingsToForm: missing data or optionsData');
      return;
    }

    console.log('Applying settings to form:', data);

    const diplomaSelect    = document.getElementById('diploma_type_id');
    const universitySelect = document.getElementById('university_id');
    const specialtySelect  = document.getElementById('specialty_id');
    const yearSelect       = document.getElementById('academic_year_id');

    // ── 1) نوع الدبلوم ──────────────────────────────────────────────────────
    if (data.diploma_type_id && diplomaSelect) {
      diplomaSelect.value = data.diploma_type_id;
    }

    // ── 2) الجامعة: نبنيها مباشرةً بناءً على الدبلوم المختار ───────────────
    if (universitySelect && data.university_id) {
      universitySelect.innerHTML = '<option value="">اختر الجامعة</option>';
      const diplomaId = parseInt(data.diploma_type_id) || null;

      let allowedUnis = optionsData.universities || [];
      if (diplomaId && optionsData.university_diploma_types && optionsData.university_diploma_types.length) {
        const allowedIds = optionsData.university_diploma_types
          .filter(udt => parseInt(udt.diploma_type_id) === diplomaId)
          .map(udt => parseInt(udt.university_id));
        if (allowedIds.length) {
          allowedUnis = allowedUnis.filter(u => allowedIds.includes(parseInt(u.id)));
        }
      }

      allowedUnis.forEach(u => {
        const opt = document.createElement('option');
        opt.value = u.id;
        opt.textContent = u.name;
        universitySelect.appendChild(opt);
      });
      universitySelect.value = data.university_id;
    }

    // ── 3) التخصص: نبنيه بناءً على الجامعة ────────────────────────────────
    if (specialtySelect && data.university_id) {
      specialtySelect.innerHTML = '<option value="">اختر التخصص (اختياري)</option>';
      const uniId = parseInt(data.university_id);

      if (optionsData.university_specialties) {
        const allowedSpecIds = optionsData.university_specialties
          .filter(us => parseInt(us.university_id) === uniId)
          .map(us => parseInt(us.specialty_id));

        (optionsData.specialties || [])
          .filter(s => allowedSpecIds.includes(parseInt(s.id)))
          .forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            specialtySelect.appendChild(opt);
          });
      }

      if (data.specialty_id) specialtySelect.value = data.specialty_id;
    }

    // ── 4) السنة الأكاديمية: نبنيها بناءً على الجامعة ────────────────────
    if (yearSelect && data.university_id) {
      yearSelect.innerHTML = '<option value="">اختر السنة الأكاديمية</option>';
      const uniId = parseInt(data.university_id);

      let allowedYearIds = [];
      if (optionsData.university_academic_years) {
        allowedYearIds = optionsData.university_academic_years
          .filter(uay => parseInt(uay.university_id) === uniId)
          .map(uay => parseInt(uay.academic_year_id));
      }

      const allYears = optionsData.academic_years || [];
      // إذا لا يوجد ربط أظهر كل السنوات (fallback)
      const filteredYears = allowedYearIds.length
        ? allYears.filter(ay => allowedYearIds.includes(parseInt(ay.id)))
        : allYears;

      filteredYears.forEach(ay => {
        const opt = document.createElement('option');
        opt.value = ay.id;
        opt.textContent = ay.name;
        yearSelect.appendChild(opt);
      });

      // ضبط السنة المختارة — هذا هو الإصلاح الجوهري
      if (data.academic_year_id) {
        yearSelect.value = String(data.academic_year_id);

        // حماية: إضافة السنة يدوياً إذا لم تكن ضمن القائمة المفلترة
        if (yearSelect.value !== String(data.academic_year_id)) {
          console.warn('السنة ' + data.academic_year_id + ' غير موجودة في القائمة المفلترة — سيتم إضافتها يدوياً');
          const yearObj = allYears.find(ay => parseInt(ay.id) === parseInt(data.academic_year_id));
          const missingOpt = document.createElement('option');
          missingOpt.value = data.academic_year_id;
          missingOpt.textContent = yearObj ? yearObj.name : 'سنة #' + data.academic_year_id;
          yearSelect.appendChild(missingOpt);
          yearSelect.value = String(data.academic_year_id);
        }

        console.log('✅ السنة المختارة:', yearSelect.value, '| المتوقع:', data.academic_year_id);
      }
    }
  }

  /**
   * بدء الاستعراض
   */
  async function startView() {
    const form = document.getElementById('studentViewSettingsForm');
    if (!form) return;

    const formData = new FormData(form);
    const data = {
      diploma_type_id: formData.get('diploma_type_id') || null,
      university_id: formData.get('university_id') || null,
      specialty_id: formData.get('specialty_id') || null,
      academic_year_id: formData.get('academic_year_id') || null,
    };

    if (!data.university_id || !data.academic_year_id) {
      showMessage('الرجاء تحديد الجامعة والسنة الأكاديمية على الأقل', 'error');
      return;
    }

    // حفظ الإعدادات في localStorage قبل البدء
    saveSettingsToCache(data);

    try {
      const response = await fetch(`${API_BASE}/admins/api/student-view/start`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          [CSRF_HEADER]: CSRF_TOKEN,
        },
        body: JSON.stringify(data),
      });

      const result = await response.json();
      if (result.success) {
        window.location.href = result.redirect || `${API_BASE}/students/`;
      } else {
        showMessage(result.message || 'حدث خطأ', 'error');
      }
    } catch (error) {
      console.error('Error starting view:', error);
      showMessage('حدث خطأ أثناء بدء الاستعراض', 'error');
    }
  }

  /**
   * إنهاء الاستعراض
   */
  async function stopView() {
    const result = await Swal.fire({
      title: 'إنهاء الاستعراض',
      text: 'هل أنت متأكد من إنهاء الاستعراض والعودة لواجهة الأدمن؟',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'نعم، إنهاء الاستعراض',
      cancelButtonText: 'تراجع',
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33'
    });
    if (!result.isConfirmed) {
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
        showMessage(result.message || 'حدث خطأ', 'error');
      }
    } catch (error) {
      console.error('Error stopping view:', error);
      showMessage('حدث خطأ أثناء إنهاء الاستعراض', 'error');
    }
  }

  /**
   * عرض رسالة
   */
  function showMessage(message, type = 'success') {
    const msgBox = document.getElementById('settingsMessage');
    if (!msgBox) return;

    msgBox.textContent = message;
    msgBox.className = `message-box ${type}`;
    msgBox.style.display = 'block';

    setTimeout(() => {
      msgBox.style.display = 'none';
    }, 5000);
  }

  /**
   * تهيئة
   */
  function init() {
    const form = document.getElementById('studentViewSettingsForm');
    if (!form) {
      console.warn('Student view form not found');
      return;
    }

    console.log('Initializing student view settings...');
    console.log('API_BASE:', API_BASE);
    console.log('CSRF_TOKEN:', CSRF_TOKEN ? 'Present' : 'Missing');
    console.log('STUDENT_VIEW_DATA available:', !!window.STUDENT_VIEW_DATA);
    console.log('STUDENT_VIEW_SERVER_SETTINGS:', window.STUDENT_VIEW_SERVER_SETTINGS);

    if (window.STUDENT_VIEW_DATA) {
      console.log('STUDENT_VIEW_DATA structure:', {
        hasDiplomaTypes: !!window.STUDENT_VIEW_DATA.diploma_types,
        hasUniversities: !!window.STUDENT_VIEW_DATA.universities,
        hasSpecialties: !!window.STUDENT_VIEW_DATA.specialties,
        hasAcademicYears: !!window.STUDENT_VIEW_DATA.academic_years,
        hasUniversityDiplomaTypes: !!window.STUDENT_VIEW_DATA.university_diploma_types,
        hasUniversitySpecialties: !!window.STUDENT_VIEW_DATA.university_specialties,
        hasUniversityAcademicYears: !!window.STUDENT_VIEW_DATA.university_academic_years
      });
    }

    // تحميل الخيارات
    loadOptions();

    // بدء الاستعراض
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      startView();
    });

    // إنهاء الاستعراض
    const stopBtn = document.getElementById('stopViewBtn');
    if (stopBtn) {
      stopBtn.addEventListener('click', stopView);
    }
  }

  // تهيئة عند تحميل الصفحة أو عند ظهور القسم
  function tryInit() {
    const form = document.getElementById('studentViewSettingsForm');
    if (!form) {
      return false;
    }

    // التحقق من أن القسم ظاهر (ليس hidden)
    const section = form.closest('[data-db-section="student-view"]');
    if (section && section.hasAttribute('hidden')) {
      return false;
    }

    // الصفحة ظاهرة الآن
    if (!form.dataset.initialized) {
      console.log('Initializing student view settings...');
      form.dataset.initialized = 'true';
      init();
      return true;
    }
    return false;
  }

  // تهيئة عند تحميل الصفحة
  function setupInit() {
    tryInit();
    
    // محاولة مرة أخرى بعد تأخير قصير (في حال كانت الصفحة مخفية)
    setTimeout(tryInit, 300);
    setTimeout(tryInit, 1000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupInit);
  } else {
    setupInit();
  }

  // مراقبة تغييرات التبويبات (إذا كان هناك نظام tabs)
  const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
      if (mutation.type === 'attributes' && (mutation.attributeName === 'hidden' || mutation.attributeName === 'class')) {
        const target = mutation.target;
        if (target.hasAttribute('data-db-section') && target.getAttribute('data-db-section') === 'student-view') {
          if (!target.hasAttribute('hidden')) {
            setTimeout(tryInit, 100);
          }
        }
      }
    });
  });

  // مراقبة قسم student-view
  const studentViewSection = document.querySelector('[data-db-section="student-view"]');
  if (studentViewSection) {
    observer.observe(studentViewSection, {
      attributes: true,
      attributeFilter: ['hidden', 'class']
    });
  }

  // مراقبة التبويبات (عند الضغط على تبويب student-view)
  document.addEventListener('click', function(e) {
    const tab = e.target.closest('.db-tab[data-db="student-view"]');
    if (tab) {
      // تأخير قصير للتأكد من أن القسم ظهر
      setTimeout(() => {
        tryInit();
      }, 150);
      setTimeout(() => {
        tryInit();
      }, 500);
    }
  });
})();
