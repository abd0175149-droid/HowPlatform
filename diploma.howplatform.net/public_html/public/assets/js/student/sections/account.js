// public/studentassets/js/profile.js

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

  initStudentProfile(Cropper);
}

function initStudentProfile(Cropper) {
  const BASE = (document.querySelector('meta[name="base-url"]')?.content || '').replace(/\/$/, '');

  const input        = document.getElementById('profileInput');
  if (!input) return;

  const editIcon     = document.querySelector('.edit-icon');
  const iconDefault  = document.getElementById('profilePictureIcon');
  const imgProfile   = document.getElementById('profilePictureImg');
  const modal        = document.getElementById('cropModal');
  const imgCrop      = document.getElementById('cropImage');
  const cancelBtn    = document.getElementById('cancelCrop');
  const saveBtn      = document.getElementById('saveCrop');
  const currentYear  = document.getElementById('currentYear');
  const editBtn      = document.getElementById('editProfileBtn');

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

  const setText = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val ?? '-';
  };
  const formatDate = d =>
    d ? new Date(d).toLocaleDateString('ar-EG', {
      year: 'numeric', month: 'long', day: 'numeric'
    }) : '-';

window.currentUser = null; // لجعله متاحًا من الكونسول
  let cropper     = null;
  let inEdit      = false;

  function fillProfile(u) {
    window.currentUser = u;

    if (u.profile_image_url) {
      imgProfile.src = u.profile_image_url;
      imgProfile.classList.remove('hidden');
      iconDefault?.classList.add('hidden');
    }

    setText('profileName', u.full_name);
      // حساب مميز
    const premiumBadge = document.getElementById('premiumBadge');
    if (u.is_premium) {
      premiumBadge?.classList.remove('hidden');
      setText('premiumUntil', formatDate(u.premium_until));
    } else {
      premiumBadge?.classList.add('hidden');
    }
    
    setText('universityName', u.university);
    setText('specialtyName', u.specialty);
    setText('academicYearName', u.academic_year);

    spans.fullName.textContent  = u.full_name;
    const genderMap = { m:'ذكر', male:'ذكر', f:'أنثى', female:'أنثى' };
    spans.gender.textContent    = genderMap[(u.gender||'').toLowerCase()] ?? '-';
    spans.birthDate.textContent = formatDate(u.birth_date);
    spans.email.textContent     = u.email;
    spans.phone.textContent     = u.phone_number;
  }

  fetch(`${BASE}/student/profile/data`, { credentials: 'include' })
    .then(r => r.ok ? r.json() : Promise.reject(r))
    .then(fillProfile)
    .catch(err => console.error('فشل في تحميل البيانات:', err));

  if (editIcon) {
    editIcon.addEventListener('click', e => {
      e.stopPropagation();
      input.click();
    });
  }

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

          fetch(`${BASE}/student/profile/upload-image`, {
            method: 'POST',
            body: fd,
            credentials: 'include'
          })
          .then(r => r.ok ? r.json() : Promise.reject(r))
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
          });
        }, 'image/png', 0.9);
    });
  }

  function enterEditMode() {
  inEdit = true;

  // تعبئة القيم في الحقول
  inputs.fullName.value  = currentUser.full_name;
  inputs.birthDate.value = currentUser.birth_date;
  inputs.email.value     = currentUser.email;
  inputs.phone.value     = currentUser.phone_number;

  // تطبيع الجنس (gender)
  const genderVal = (currentUser.gender || '').toLowerCase();
  if (genderVal === 'male' || genderVal === 'm' || genderVal === 'ذكر') {
    inputs.gender.value = 'male';
  } else if (genderVal === 'female' || genderVal === 'f' || genderVal === 'أنثى') {
    inputs.gender.value = 'female';
  } else {
    inputs.gender.value = ''; // لا شيء محدد
  }

  // إخفاء السبانات وإظهار الحقول
  Object.values(spans).forEach(el => el.classList.add('hidden'));
  Object.values(inputs).forEach(el => el.classList.remove('hidden'));

  // تعديل الزر
  editBtn.innerHTML = '<i class="fas fa-save"></i> حفظ التعديلات';
  editBtn.classList.replace('btn-outline-primary','btn-success');
}


 function exitEditMode(updated) {
  inEdit = false;

  // تحديث currentUser بالقيم الجديدة
  currentUser.full_name    = updated.full_name;
  currentUser.gender       = updated.gender;
  currentUser.birth_date   = updated.birth_date;
  currentUser.email        = updated.email;
  currentUser.phone_number = updated.phone_number;

  // تحديث النصوص في العرض
  spans.fullName.textContent  = currentUser.full_name;

  const genderMap = { m:'ذكر', male:'ذكر', f:'أنثى', female:'أنثى' };
  spans.gender.textContent    = genderMap[(currentUser.gender || '').toLowerCase()] ?? '-';

  spans.birthDate.textContent = formatDate(currentUser.birth_date);
  spans.email.textContent     = currentUser.email;
  spans.phone.textContent     = currentUser.phone_number;

  // إظهار spans وإخفاء inputs
  Object.values(spans).forEach(el => el.classList.remove('hidden'));
  Object.values(inputs).forEach(el => el.classList.add('hidden'));

  // إعادة زر التعديل إلى حالته
  editBtn.innerHTML = '<i class="fas fa-edit"></i> تعديل الملف الشخصي';
  editBtn.classList.replace('btn-success', 'btn-outline-primary');
}

  async function saveProfile() {
    const payload = {
        full_name:    inputs.fullName.value,
        gender:       inputs.gender.value,
        birth_date:   inputs.birthDate.value,
        email:        inputs.email.value,
        phone_number: inputs.phone.value,
    };

    try {
      const res = await fetch(`${BASE}/student/profile/update`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(payload)
      });
      if (!res.ok) throw new Error();

      const updated = await res.json();
      exitEditMode(updated);
    } catch {
      console.error('فشل حفظ التعديلات');
    }
  }

  if (editBtn) {
    editBtn.addEventListener('click', () => {
      if (!inEdit) enterEditMode();
      else saveProfile();
    });
  }
}

export default {};
