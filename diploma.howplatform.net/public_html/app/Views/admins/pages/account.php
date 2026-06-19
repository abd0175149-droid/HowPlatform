<style>
.hidden {
    display: none !important;
}

/* تنسيق الحاوية العامة */
.profile-container {
   /* max-width: 1000px;*/
    margin: 0 auto;
    background: white;
    border-radius: var(--border-radius);
    box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

/* رأس الصفحة */
.profile-header-section {
    background: linear-gradient(135deg, var(--primary-darker), var(--primary-dark));
    color: white;
    padding: 25px 30px;
    text-align: center;
}

.profile-header-section h1 {
    font-size: 1.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    margin-bottom: 10px;
}

.profile-header-section p {
    font-size: 1.1rem;
    opacity: 0.9;
}

/* بطاقة الملف الشخصي الرئيسية */
.profile-card {
    padding: 30px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
    background: white;
    border-bottom: 1px solid #eee;
}

/* الصورة الدائرية */
.profile-picture {
    position: relative;
    width: 180px;
    height: 180px;
    margin: 0 auto 15px;
    border-radius: 50%;
    border: 3px solid var(--primary);
    overflow: hidden;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

#profilePictureIcon,
#profilePictureImg {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

#profilePictureIcon {
    position: absolute;
    top: 0;
    left: 0;
    background: var(--primary);
    color: white;
    font-size: 4rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.file-input {
    position: absolute;
    inset: 0;
    opacity: 0;
    z-index: 2;
    cursor: pointer;
}

.edit-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: var(--transition);
    z-index: 3;
    pointer-events: none;
}

.profile-picture:hover .edit-icon {
    opacity: 1;
    pointer-events: auto;
}

/* معلومات الحساب */
.profile-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
    text-align: center;
}

.profile-name {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-darker);
}

.actions-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    flex-wrap: wrap;
    justify-content: center;
}

.btn {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 8px;
    border: none;
}

.btn-outline-primary {
    background: transparent;
    border: 1px solid var(--primary);
    color: var(--primary);
}

.btn-outline-primary:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(33, 158, 188, 0.2);
}

.btn-success {
    background: var(--accent);
    color: var(--primary-darker);
    border: none;
}

.btn-success:hover {
    background: var(--accent-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 183, 3, 0.3);
}

.account-type {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: var(--accent-light);
    border-radius: 20px;
    color: var(--text);
    font-weight: 600;
}

/* معلومات الميتا */
.profile-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    justify-content: center;
    margin-top: 15px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #8ecae64f;
    padding: 10px 16px;
    border-radius: 20px;
    font-size: 1rem;
}

.meta-item i {
    color: var(--primary);
}

/* بطاقات المعلومات - التصميم الجديد مشابه للصورة */
.info-section {
    display: flex;
    flex-direction: column;
    gap: 25px;
    padding: 30px 0;
}

.info-card {
    background: white;
    padding: 25px;
    border-radius: var(--border-radius);
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
    transition: var(--transition);
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.5rem;
    color: var(--primary-darker);
    margin-bottom: 25px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--primary-light);
}

/* تصميم الجدول الجديد - مشابه للصورة */
.info-table {
    width: 100%;
    border-collapse: collapse;
}

.info-table tr {
    border-bottom: 1px solid #eee;
}

.info-table tr:last-child {
    border-bottom: none;
}

.info-table td {
    padding: 15px 10px;
}
.info-table td i {
padding-left: 5px;
}

.info-label {
    width: 35%;
    text-align: right;
    color: var(--text-light);
    font-weight: 600;
    padding-right: 20px;
}

.info-value {
    text-align: right;
    padding-right: 20px;
}

.info-value span {
    display: block;
    padding: 5px 0;
}

.form-control {
    padding: 12px 15px;
    border-radius: 8px;
    border: 1px solid #ddd;
    background: white;
    font-size: 1rem;
    transition: var(--transition);
    width: 100%;
    max-width: 400px;
    text-align: right;
}

.form-control:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(33, 158, 188, 0.2);
}

/* التذييل */
.footer {
    text-align: center;
    padding: 20px;
    color: var(--text-light);
    font-size: 0.9rem;
    border-top: 1px solid #eee;
    background: #f8fafc;
}

/* مودال القص */
.modal {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.modal.hidden {
    display: none !important;
}

.modal-content {
    background: white;
    padding: 25px;
    border-radius: var(--border-radius);
    max-width: 90vw;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    gap: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

#cropImage {
    max-width: 80vw;
    max-height: 60vh;
    background: #f3f3f3;
    border-radius: 8px;
}

.modal-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #565e64;
}

/* التصميم المتجاوب */
@media (max-width: 768px) {
    .info-section {
        padding: 20px;
    }
    
    .profile-header-section h1 {
        font-size: 1.5rem;
    }
    
    .profile-name {
        font-size: 1.7rem;
    }
    
    .profile-picture {
        width: 150px;
        height: 150px;
    }
    
    .actions-row {
        flex-direction: column;
    }
    
    .info-table {
        display: block;
    }
    
    .info-table tr {
        display: block;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }
    
    .info-table td {
        display: block;
        width: 100% !important;
        padding: 8px 0;
        text-align: right;
    }
    
    .info-label {
        padding-right: 0;
        font-weight: 700;
        color: var(--primary);
    }
    
    .info-value {
        padding-right: 0;
    }
}
</style>
<div class="profile-container">
  <!-- رأس الصفحة -->
  <div class="profile-header-section">
    <h1><i class="fas fa-user-circle"></i> الملف الشخصي</h1>
    <p>إدارة معلومات حسابك الشخصي على منصة كيف التعليمية</p>
  </div>

  <!-- بطاقة الملف الشخصي الرئيسية -->
  <div class="profile-card">
    <div class="profile-picture" id="profilePictureWrapper">
      <i class="fas fa-user" id="profilePictureIcon"></i>
      <img id="profilePictureImg" class="hidden" alt="صورة الملف الشخصي"/>
      <div class="edit-icon"><i class="fas fa-pen"></i></div>
      <input type="file" id="profileInput" accept="image/*" class="file-input"/>
    </div>

    <div class="profile-info">
      <h2 class="profile-name" id="profileName">…</h2>
      <div class="actions-row">
        <div class="account-type hidden" id="premiumBadge">
          <i class="fas fa-crown"></i>
          حساب مميز حتى <span id="premiumUntil"></span>
        </div>
        <button class="btn btn-outline-primary" id="editProfileBtn">
          <i class="fas fa-edit"></i> تعديل الملف الشخصي
        </button>
      </div>
</div>
      <div class="profile-meta">
        <div class="meta-item">
          <i class="fas fa-calendar-alt"></i>
          <span id="academicYearName"></span>
        </div>
        <div class="meta-item">
          <i class="fas fa-graduation-cap"></i>
          <span id="specialtyName"></span>
        </div>
        <div class="meta-item">
          <i class="fas fa-university"></i>
          <span id="universityName"></span>
        </div>
      </div>
    </div>
  </div>

  <!-- قسم بطاقات المعلومات -->
  <div class="info-section">
    <!-- بطاقة المعلومات الشخصية -->
    <div class="info-card">
      <h2 class="section-title"><i class="fas fa-user-tie"></i> المعلومات الشخصية</h2>
      <table class="info-table">
        <tr>
          <td class="info-label"><i class="fas fa-id-card"></i> الاسم الكامل</td>
          <td class="info-value">
            <span id="fullNameSpan"></span>
            <input type="text" id="fullNameInput" class="hidden form-control" />
          </td>
        </tr>
        <tr>
          <td class="info-label"><i class="fas fa-venus-mars"></i> الجنس</td>
          <td class="info-value">
            <span id="genderSpan"></span>
            <select id="genderInput" class="hidden form-control">
              <option value="">— اختر —</option>
              <option value="male">ذكر</option>
              <option value="female">أنثى</option>
            </select>
          </td>
        </tr>
        <tr>
          <td class="info-label"><i class="fas fa-birthday-cake"></i> تاريخ الميلاد</td>
          <td class="info-value">
            <span id="birthDateSpan"></span>
            <input type="date" id="birthDateInput" class="hidden form-control" />
          </td>
        </tr>
      </table>
    </div>

    <!-- بطاقة التواصل -->
    <div class="info-card">
      <h2 class="section-title"><i class="fas fa-address-book"></i> معلومات التواصل</h2>
      <table class="info-table">
        <tr>
          <td class="info-label"><i class="fas fa-envelope"></i> البريد الإلكتروني</td>
          <td class="info-value">
            <span id="emailSpan"></span>
            <input type="email" id="emailInput" class="hidden form-control" />
          </td>
        </tr>
        <tr>
          <td class="info-label"><i class="fas fa-phone"></i> رقم الهاتف</td>
          <td class="info-value">
            <span id="phoneNumberSpan"></span>
            <input type="tel" id="phoneNumberInput" class="hidden form-control" />
          </td>
        </tr>
      </table>
    </div>
  </div>
</div>
  <!-- تذييل الصفحة -->
  <div class="footer">
    <p>© <span id="currentYear"></span> منصة كيف التعليمية - جميع الحقوق محفوظة</p>
  </div>


<!-- Cropper Modal -->
<div id="cropModal" class="modal hidden">
  <div class="modal-content">
    <img id="cropImage" src="" alt="قص الصورة">
    <div class="modal-actions">
      <button class="btn btn-secondary" id="cancelCrop">إلغاء</button>
      <button class="btn btn-primary"   id="saveCrop">حفظ</button>
    </div>
  </div>
</div>