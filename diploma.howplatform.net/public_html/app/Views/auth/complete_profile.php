<style>
    .form-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 2rem;
    }
    
    .form-group {
      flex: 1 1 calc(50% - 1rem);
      min-width: 250px;
    }
    
    .full-width {
      flex: 1 1 100%;
    }
    
    /* على الشاشات الصغيرة */
    @media (max-width: 768px) {
      .form-group {
        flex: 1 1 100%;
      }
    }
    .alert-fill-warning {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 1rem 1.5rem;
      margin-bottom: 1.5rem;
      border-radius: 12px;
      color: #b53a2d;
      background-color: rgba(255, 87, 34, 0.1);
      border-left: 5px solid #b53a2d;
      font-weight: bold;
      font-size: 1.1rem;
      animation: pulse-warning 1s ease-in-out infinite alternate;
    }
    
    @keyframes pulse-warning {
      from {
        transform: scale(1);
        background-color: rgba(255, 87, 34, 0.08);
      }
      to {
        transform: scale(1.02);
        background-color: rgba(255, 87, 34, 0.2);
      }
    }

    /* ستايل صفحة تسجيل الدخول */
    .profile-main {
        display: flex;
        min-width: 80vw;
        justify-content: center;
        align-items: center;
        min-height: 80vh;
        padding: 2rem 5%;
    }
    
    .profile-container {
        display: flex;
        gap: 4rem;
        width: 100%;
        max-width: 1200px;
        align-items: center;
        justify-content: center;
    }
    
    .profile-card {
        flex: 1;
        background: var(--background);
        border-radius: 20px;
        padding: 2.5rem;
        box-shadow: 0 15px 30px rgba(2, 48, 71, 0.1);
        border: 1px solid rgba(142, 202, 230, 0.3);
        animation: fadeIn 0.8s ease;
        max-width: 500px;
    }
    
    .profile-title {
        font-size: 2.2rem;
        margin-bottom: 1.8rem;
        color: var(--primary-darker);
        text-align: center;
        position: relative;
        padding-bottom: 1rem;
    }
    
    .profile-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 100px;
        height: 4px;
        background: linear-gradient(to right, var(--accent), var(--accent-dark));
        border-radius: 10px;
    }
    
    .error-message {
        background: rgba(251, 133, 0, 0.1);
        color: var(--accent-dark);
        padding: 1rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        border-left: 4px solid var(--accent-dark);
        display: flex;
        align-items: center;
        gap: 10px;
        animation: fadeIn 0.5s ease;
    }
    .success-message {
    background: rgba(0, 200, 83, 0.1);
    color: #2e7d32;
    padding: 1rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    border-left: 4px solid #2e7d32;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: fadeIn 0.5s ease;
    }

    
    .form-group {
        margin-bottom: 1.8rem;
    }
    
    .form-label {
        display: block;
        margin-bottom: 0.8rem;
        font-weight: 700;
        color: var(--primary-dark);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .form-input {
        width: 100%;
        padding: 1rem 1.2rem;
        border: 2px solid rgba(142, 202, 230, 0.5);
        border-radius: 12px;
        font-size: 1.1rem;
        transition: all 0.3s ease;
        background: rgba(142, 202, 230, 0.05);
    }
    
    .form-input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(33, 158, 188, 0.2);
        outline: none;
    }
    
    .form-actions {
        margin-top: 2.5rem;
        text-align: center;
    }
    
    .login-btn {
        padding: 1rem 3rem;
        font-size: 1.2rem;
        border-radius: 12px;
        transition: all 0.3s ease;
        width: 100%;
        justify-content: center;
    }
    
    .login-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 7px 20px rgba(33, 158, 188, 0.4);
    }
    
    .login-links {
        margin-top: 2rem;
        text-align: center;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .login-links a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 700;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .login-links a:hover {
        color: var(--primary-darker);
    }
    
    .login-links a::after {
        content: '';
        position: absolute;
        bottom: -2px;
        right: 0;
        width: 0;
        height: 2px;
        background: var(--accent);
        transition: width 0.3s ease;
    }
    
    .login-links a:hover::after {
        width: 100%;
        left: 0;
    }
    
    .login-illustration {
        flex: 1;
        max-width: 500px;
        animation: slideInRight 0.8s ease;
    }
    
    .hero-illustration {
        background: linear-gradient(135deg, var(--accent-light), var(--accent));
    }
    
    .hero-illustration-inner {
        background-color: var(--background);
        height: 350px;
    }
    
    .edu-icons {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100%;
        gap: 40px;
        flex-wrap: wrap;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideInRight {
        from { opacity: 0; transform: translateX(50px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    /* التجاوبية */
    @media (max-width: 992px) {
        .profile-container {
            flex-direction: column;
            gap: 3rem;
        }
        
        .profile-card {
            max-width: 600px;
            width: 100%;
        }
        
        .login-illustration {
            max-width: 100%;
        }
    }
    
    @media (max-width: 576px) {
        .profile-card {
            padding: 1.8rem;
        }
        
        .profile-title {
            font-size: 1.8rem;
        }
    }
    .alert-fill-warning {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        border-radius: 12px;
        color: #b53a2d;
        background-color: rgba(255, 87, 34, 0.1);
        border-left: 5px solid #b53a2d;
        font-weight: bold;
        font-size: 1.1rem;
        animation: pulse-warning 1s ease-in-out infinite alternate;
    }
    
    @keyframes pulse-warning {
        from {
            transform: scale(1);
            background-color: rgba(255, 87, 34, 0.08);
        }
        to {
            transform: scale(1.02);
            background-color: rgba(255, 87, 34, 0.2);
        }
    }

</style>
<div class="profile-main">
  <div class="profile-container">
    <div class="profile-card" style="width: 100%; max-width: 900px;">
      <h2 class="profile-title">استكمال البيانات الشخصية</h2>
      
      <div class="alert-fill-warning">
        <i class="fas fa-exclamation-triangle"></i> يُرجى تعبئة كافة الحقول المطلوبة لإكمال التسجيل.
      </div>

      <div id="profile-msg"></div>

      <form id="profileForm" class="register-form" method="post" action="<?= site_url('auth/complete-profile') ?>">
        <?= csrf_field() ?>

        <div class="form-grid">
          <!-- الاسم -->
          <div class="form-group">
            <label for="name" class="form-label"><i class="fas fa-user"></i> الاسم:</label>
            <input type="text" name="name" id="name" value="<?= esc($user['name']) ?>" required class="form-input">
          </div>

          <!-- البريد -->
          <div class="form-group">
            <label for="email" class="form-label"><i class="fas fa-envelope"></i> البريد الإلكتروني:</label>
            <input type="email" id="email" value="<?= esc($user['email']) ?>" class="form-input" disabled>
          </div>

          <!-- الهاتف -->
          <div class="form-group">
            <label for="phone_number" class="form-label"><i class="fas fa-phone"></i> رقم الهاتف:</label>
            <input type="text" name="phone_number" id="phone_number" class="form-input" required>
          </div>

          <!-- نوع الدبلوم -->
          <div class="form-group">
            <label for="diploma_type_id" class="form-label"><i class="fas fa-clipboard-list"></i> نوع الدبلوم:</label>
            <select name="diploma_type_id" id="diploma_type_id" required class="form-input">
              <option value="">اختر نوع الدبلوم</option>
              <?php foreach ($diploma_types as $d): ?>
                <option value="<?= $d['id'] ?>"><?= esc($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- الجامعة -->
          <div class="form-group">
            <label for="university_id" class="form-label"><i class="fas fa-university"></i> الجامعة:</label>
            <select name="university_id" id="university_id" required class="form-input">
              <option value="">اختر الجامعة</option>
              <?php foreach ($universities as $u): ?>
                <option value="<?= $u['id'] ?>" data-type="<?= $u['id'] == 5 ? 'during' : 'before' ?>">
                  <?= esc($u['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- التخصص -->
          <div class="form-group">
            <label for="specialty_id" class="form-label"><i class="fas fa-graduation-cap"></i> التخصص:</label>
            <select name="specialty_id" id="specialty_id" required class="form-input">
              <option value="">اختر التخصص</option>
              <!-- يتم تعبئته ديناميكياً -->
            </select>
          </div>

          <!-- العام الدراسي -->
          <div class="form-group">
            <label for="academic_year_id" class="form-label"><i class="fas fa-calendar-alt"></i> العام الدراسي:</label>
            <select name="academic_year_id" id="academic_year_id" required class="form-input">
              <option value="">اختر العام الدراسي</option>
              <!-- سيتم تعبئتها ديناميكيًا -->
            </select>
          </div>

          <!-- الجنس -->
          <div class="form-group">
            <label for="gender" class="form-label"><i class="fas fa-venus-mars"></i> الجنس:</label>
            <select name="gender" id="gender" required class="form-input">
              <option value="">اختر الجنس</option>
              <option value="male">ذكر</option>
              <option value="female">أنثى</option>
            </select>
          </div>

          <!-- تاريخ الميلاد -->
          <div class="form-group">
            <label for="birth_date" class="form-label"><i class="fas fa-calendar-day"></i> تاريخ الميلاد:</label>
            <input type="date" name="birth_date" id="birth_date" class="form-input" required>
          </div>
        </div>

        <!-- زر الإرسال -->
        <div class="form-actions">
          <button type="submit" class="btn btn-primary login-btn">
            <i class="fas fa-check-circle"></i> حفظ ومتابعة
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  /* بيانات من الـ PHP */
  const universities   = <?= json_encode($universities_map) ?>;
  const specialties    = <?= json_encode($specialties_map) ?>;
  const academicYears  = <?= json_encode($academic_years_map) ?>;

  /* عناصر الـ DOM */
  const diplomaSelect    = document.getElementById('diploma_type_id');
  const universitySelect = document.getElementById('university_id');
  const specialtySelect  = document.getElementById('specialty_id');
  const yearSelect       = document.getElementById('academic_year_id');
  const form             = document.getElementById('profileForm');
  const submitBtn        = form.querySelector('button[type="submit"]');
  const msgBox           = document.getElementById('profile-msg');

  /* تغيير نوع الدبلوم → تعبئة الجامعات */
  diplomaSelect.addEventListener('change', () => {
      const diplomaId = parseInt(diplomaSelect.value);
      universitySelect.innerHTML = '<option value="">اختر الجامعة</option>';
      universities.filter(u => +u.diploma_type_id === diplomaId)
                  .forEach(u => {
                      const opt = document.createElement('option');
                      opt.value = u.university_id;
                      opt.textContent = u.university_name;
                      universitySelect.appendChild(opt);
                  });

      specialtySelect.innerHTML = '<option value="">اختر التخصص</option>';
      yearSelect.innerHTML      = '<option value="">اختر العام الدراسي</option>';
  });

  /* تغيير الجامعة → تعبئة التخصّصات والأعوام */
  universitySelect.addEventListener('change', () => {
      const uniId = parseInt(universitySelect.value);

      /* التخصصات */
      specialtySelect.innerHTML = '<option value="">اختر التخصص</option>';
      specialties.filter(s => +s.university_id === uniId)
                 .forEach(s => {
                     const opt = document.createElement('option');
                     opt.value = s.specialty_id;
                     opt.textContent = s.specialty_name;
                     specialtySelect.appendChild(opt);
                 });

      /* الأعوام الدراسية */
      yearSelect.innerHTML = '<option value="">اختر العام الدراسي</option>';
      academicYears.filter(y => +y.university_id === uniId)
                   .forEach(y => {
                       const opt = document.createElement('option');
                       opt.value = y.year_id;
                       opt.textContent = y.year_name;
                       yearSelect.appendChild(opt);
                   });
  });

  /* إرسال النموذج AJAX */
  form.addEventListener('submit', e => {
      e.preventDefault();

      const data = new FormData(form);
      submitBtn.disabled = true;
      submitBtn.textContent = 'جاري الحفظ...';

      fetch("<?= site_url('auth/complete-profile') ?>", {
          method : 'POST',
          body   : data,
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(r => r.json())
      .then(res => {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> حفظ ومتابعة';

          msgBox.innerHTML = `
              <div class="${res.success ? 'success-message' : 'error-message'}">
                <i class="fas ${res.success ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                ${res.message ?? 'حصل خطأ غير متوقع'}
              </div>`;

          if (res.success && res.redirect) window.location.href = res.redirect;
      })
      .catch(err => {
          console.error(err);
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> حفظ ومتابعة';
          msgBox.innerHTML = '<div class="error-message">فشل الاتصال بالخادم، حاول مرة أخرى.</div>';
      });
  });
});
</script>
