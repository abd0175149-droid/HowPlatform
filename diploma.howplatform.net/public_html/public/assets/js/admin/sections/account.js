
// عند تحميل القسم استورد Cropper.js من CDN ثم شغّل التهيئة
export async function init() {
  const { default: Cropper } = await import(
    'https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.esm.js'
  );

  const cssHref = 'https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css';
  if (!document.querySelector(`link[href="${cssHref}"]`)) {
    const link = document.createElement('link');
    link.rel  = 'stylesheet';
    link.href = cssHref;
    document.head.appendChild(link);
  }

  initAccount(Cropper);
}

function initAccount(Cropper) {
  const BASE = (document.querySelector('meta[name="base-url"]')?.content || '').replace(/\/$/, '');

  // العناصر الأساسية
  const input        = document.getElementById('profileInput');
  if (!input) return;  // لست في صفحة الحساب

  const editIcon     = document.querySelector('.edit-icon');
  const iconDefault  = document.getElementById('profilePictureIcon');
  const imgProfile   = document.getElementById('profilePictureImg');
  const modal        = document.getElementById('cropModal');
  const imgCrop      = document.getElementById('cropImage');
  const cancelBtn    = document.getElementById('cancelCrop');
  const saveBtn      = document.getElementById('saveCrop');
  const currentYear  = document.getElementById('currentYear');
  const editBtn      = document.getElementById('editProfileBtn');

  // spans لعرض البيانات و inputs للتحرير
  const spans = {
    fullName:   document.getElementById('fullNameSpan'),
    gender:     document.getElementById('genderSpan'),
    birthDate:  document.getElementById('birthDateSpan'),
    email:      document.getElementById('emailSpan'),
    phone:      document.getElementById('phoneNumberSpan'),
  };
  const inputs = {
    fullName:   document.getElementById('fullNameInput'),
    gender:     document.getElementById('genderInput'),
    birthDate:  document.getElementById('birthDateInput'),
    email:      document.getElementById('emailInput'),
    phone:      document.getElementById('phoneNumberInput'),
  };

  if (currentYear) currentYear.textContent = new Date().getFullYear();

  // دوال مساعدة
  const setText = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val ?? '-';
  };
  const formatDate = d =>
    d
      ? new Date(d).toLocaleDateString('ar-SA-u-ca-gregory', {
          year: 'numeric', month: 'long', day: 'numeric'
        })
      : '-';

  let currentUser = null;
  let cropper     = null;
  let inEdit      = false;

  // يملأ البيانات في الصفحة
  function fillProfile(u) {
    currentUser = u;

    // صورة الملف الشخصي
    if (u.profile_image_url) {
      imgProfile.src = u.profile_image_url;
      imgProfile.classList.remove('hidden');
      iconDefault?.classList.add('hidden');
    }

    // الاسم
    setText('profileName', u.name);

    // حساب مميز
    const premiumBadge = document.getElementById('premiumBadge');
    if (u.is_premium) {
      premiumBadge?.classList.remove('hidden');
      setText('premiumUntil', formatDate(u.premium_until));
    } else {
      premiumBadge?.classList.add('hidden');
    }

    // المراجع
    setText('universityName',   u.university);
    setText('specialtyName',    u.specialty);
    setText('academicYearName', u.academic_year);

    // spans المحتوى
    spans.fullName.textContent  = u.full_name;
    const genderMap = { m:'ذكر', male:'ذكر', f:'أنثى', female:'أنثى' };
    spans.gender.textContent    = genderMap[(u.gender||'').toLowerCase()] ?? '-';
    spans.birthDate.textContent = formatDate(u.birth_date);
    spans.email.textContent     = u.email;
    spans.phone.textContent     = u.phone_number;

    // حالة التفعيل
    setText('verificationStatus', u.is_verified ? 'مفعّل' : 'غير مفعّل');
  }

  // جلب بيانات المستخدم
fetch(`${BASE}/api/profile`, { credentials: 'include' })
  .then(r => (r.ok ? r.json() : Promise.reject(r)))
  .then(fillProfile)
  .catch(err => {
    console.error('Failed to load profile:', err);
    showError('يرجى المحاولة لاحقًا', 'تعذّر تحميل بيانات الحساب');
  });



  // ** جديد **
  // عند الضغط على أيقونة التحرير نفتح اختيار الملف حتى لو كانت صورة موجودة
  if (editIcon) {
    editIcon.addEventListener('click', e => {
      e.stopPropagation();
      input.click();
    });
  }
  // ** نهاية الجديد **

  // إعداد قص الصورة
  if (modal && imgCrop && cancelBtn && saveBtn) {
    input.addEventListener('change', e => {
      const file = e.target.files[0];
      if (!file) return;

      imgCrop.src = URL.createObjectURL(file);
      modal.classList.remove('hidden');

      cropper?.destroy();
      cropper = new Cropper(imgCrop, {
        aspectRatio: 1,
        viewMode:   1,
        dragMode:   'move',
        background: false
      });
    });

    cancelBtn.addEventListener('click', () => {
      modal.classList.add('hidden');
      cropper?.destroy();
      cropper = null;
      input.value = '';
    });

    saveBtn.addEventListener('click', () => {
      if (!cropper) return;

      cropper.getCroppedCanvas({ width: 300, height: 300 })
        .toBlob(blob => {
          const fd = new FormData();
          fd.append('image', blob, 'profile.png');

          fetch(`${BASE}/api/profile/upload-image`, {
            method:      'POST',
            body:         fd,
            credentials: 'include',
          })
          .then(r => (r.ok ? r.json() : Promise.reject(r)))
          .then(res => {
            imgProfile.src = `${res.profile_image_url}?t=${Date.now()}`;
            imgProfile.classList.remove('hidden');
            iconDefault?.classList.add('hidden');
            modal.classList.add('hidden');
            cropper.destroy();
            cropper = null;
            input.value = '';
          })
          .catch(err => {
          console.error('Upload failed:', err);
        showError('يرجى المحاولة مرة أخرى', 'فشل رفع الصورة الجديدة');
        });
        }, 'image/png', 0.9);
    });
  }

  // الدخول في وضع التحرير
  function enterEditMode() {
    inEdit = true;
    // ملء الحقول
    inputs.fullName.value  = currentUser.full_name;
    inputs.gender.value    = currentUser.gender;
    inputs.birthDate.value = currentUser.birth_date;
    inputs.email.value     = currentUser.email;
    inputs.phone.value     = currentUser.phone_number;

    // تبديل spans ↔ inputs
    Object.values(spans).forEach(el => el.classList.add('hidden'));
    Object.values(inputs).forEach(el => el.classList.remove('hidden'));

    // تعديل شكل الزر
    editBtn.innerHTML = '<i class="fas fa-save"></i> حفظ التعديلات';
    editBtn.classList.replace('btn-outline-primary','btn-success');
  }

  // الخروج من وضع التحرير وتحديث الواجهة
  function exitEditMode(updated) {
    inEdit = false;
    currentUser = { ...currentUser, ...updated };

    spans.fullName.textContent  = currentUser.full_name;
    const genderMap = { m:'ذكر', male:'ذكر', f:'أنثى', female:'أنثى' };
    spans.gender.textContent    = genderMap[(currentUser.gender||'').toLowerCase()] ?? '-';
    spans.birthDate.textContent = formatDate(currentUser.birth_date);
    spans.email.textContent     = currentUser.email;
    spans.phone.textContent     = currentUser.phone_number;

    Object.values(spans).forEach(el => el.classList.remove('hidden'));
    Object.values(inputs).forEach(el => el.classList.add('hidden'));

    editBtn.innerHTML = '<i class="fas fa-edit"></i> تعديل الملف الشخصي';
    editBtn.classList.replace('btn-success','btn-outline-primary');
  }

  // حفظ التعديلات عبر API
 async function saveProfile() {
  const payload = {
    full_name:    inputs.fullName.value,
    gender:       inputs.gender.value,
    birth_date:   inputs.birthDate.value,
    email:        inputs.email.value,
    phone_number: inputs.phone.value,
  };

  try {
    const res = await fetch(`${BASE}/api/profile/update`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload),
    });
    if (!res.ok) throw new Error();

    const updated = await res.json();
    exitEditMode(updated);

    showSuccess('تم حفظ التعديلات بنجاح', undefined, true);
  } catch {
    showError('حاول لاحقًا', 'فشل حفظ التعديلات');
  }
}

  // ربط زر التعديل/حفظ
  if (editBtn) {
    editBtn.addEventListener('click', () => {
      if (!inEdit) enterEditMode();
      else saveProfile();
    });
  }
}

// تصدير فارغ لضمان عمل import()
export default {};
