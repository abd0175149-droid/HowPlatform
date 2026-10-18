<style>
.hidden {
    display: none !important;
}

/* تنسيق الحاوية العامة */
.profile-container {
   /* max-width: 800px;*/
    margin: 0 auto;
    background: var(--surface-0);
    border: 0.8px solid var(--border-color);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

/* رأس الصفحة */
.profile-header-section {
    background: var(--surface-1);
    color: var(--text-strong);
    padding: 20px 24px;
    text-align: center;
    border-bottom: 0.8px solid var(--border-color);
}

.profile-header-section h1 {
    font-size: 1.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-bottom: 8px;
}

.profile-header-section p {
    font-size: 1.1rem;
    opacity: 0.9;
}

/* بطاقة الملف الشخصي الرئيسية */
.profile-card {
    padding: 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    background: var(--surface-0);
    border-bottom: 0.8px solid var(--border-color);
}

/* الصورة الدائرية */
.profile-picture {
    position: relative;
    width: 144px;
    height: 144px;
    margin: 0 auto 12px;
    border-radius: 50%;
    border: 2.4px solid var(--primary);
    overflow: hidden;
    cursor: pointer;
    box-shadow: var(--shadow-sm);
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
    width: 32px;
    height: 32px;
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
    gap: 12px;
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
    gap: 8px;
    margin-bottom: 8px;
    flex-wrap: wrap;
    justify-content: center;
}

.btn {
    padding: 8px 16px;
    border-radius: 6.4px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 6.4px;
    border: none;
}

.btn-outline-primary {
    background: transparent;
    border: 0.8px solid var(--primary);
    color: var(--primary);
}

.btn-outline-primary:hover {
    background: var(--primary);
    color: var(--text-inverse);
    transform: translateY(-1.6px);
    border-color: var(--primary-300);
    box-shadow: var(--shadow-sm);
}

.btn-success {
    background: var(--accent);
    color: var(--primary-darker);
    border: none;
}

.btn-success:hover {
    background: var(--accent-dark);
    transform: translateY(-1.6px);
    box-shadow: var(--shadow-sm);
}

.account-type {
    display: flex;
    align-items: center;
    gap: 6.4px;
    padding: 6.4px 12.8px;
    background: var(--accent-light);
    border-radius: 16px;
    color: var(--text);
    font-weight: 600;
}

/* معلومات الميتا */
.profile-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: center;
    margin-top: 12px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 6.4px;
    background: var(--primary-50);
    color: var(--primary-700);
    border: 0.8px solid var(--primary-300);
    padding: 8px 12.8px;
    border-radius: 16px;
    font-size: 1rem;
}

.meta-item i {
    color: var(--primary);
}

/* بطاقات المعلومات - التصميم الجديد مشابه للصورة */
.info-section {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 24px 0;
}

.info-card {
    background: var(--surface-0);
    padding: 20px;
    border: 0.8px solid var(--border-color);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
}

.info-card:hover {
    transform: translateY(-1.6px);
    border-color: var(--primary-300);
    box-shadow: var(--shadow-sm);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 9.6px;
    font-size: 1.5rem;
    color: var(--primary-darker);
    margin-bottom: 20px;
    padding-bottom: 9.6px;
    border-bottom: 1.6px solid var(--primary-light);
}

/* تصميم الجدول الجديد - مشابه للصورة */
.info-table {
    width: 100%;
    border-collapse: collapse;
}

.info-table tr {
    border-bottom: 0.8px solid var(--border-color);
}

.info-table tr:last-child {
    border-bottom: none;
}

.info-table td {
    padding: 12px 8px;
}
.info-table td i {
padding-left: 4px;
}

.info-label {
    width: 35%;
    text-align: right;
    color: var(--text-muted);
    font-weight: 600;
    padding-right: 16px;
}

.info-value {
    text-align: right;
    padding-right: 16px;
}

.info-value span {
    display: block;
    padding: 4px 0;
}

.form-control {
    padding: 9.6px 12px;
    border-radius: 6.4px;
    border: 0.8px solid var(--border-color);
    background: var(--surface-0);
    color: var(--text);
    font-size: 1rem;
    transition: var(--transition);
    width: 100%;
    max-width: 320px;
    text-align: right;
}

.form-control:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: var(--focus-ring);
}

/* التذييل */
.footer {
    text-align: center;
    padding: 16px;
    color: var(--text-muted);
    font-size: 0.9rem;
    border-top: 0.8px solid var(--border-color);
    background: var(--surface-1);
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
    background: var(--surface-0);
    padding: 20px;
    border-radius: var(--border-radius);
    max-width: 90vw;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    gap: 16px;
    box-shadow: var(--shadow-lg);
}

#cropImage {
    max-width: 80vw;
    max-height: 60vh;
    background: var(--surface-2);
    border-radius: 6.4px;
}

.modal-actions {
    display: flex;
    gap: 12px;
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
        padding: 16px;
    }
    
    .profile-header-section h1 {
        font-size: 1.5rem;
    }
    
    .profile-name {
        font-size: 1.7rem;
    }
    
    .profile-picture {
        width: 120px;
        height: 120px;
    }
    
    .actions-row {
        flex-direction: column;
    }
    
    .info-table {
        display: block;
    }
    
    .info-table tr {
        display: block;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 0.8px solid var(--border-color);
    }
    
    .info-table td {
        display: block;
        width: 100% !important;
        padding: 6.4px 0;
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