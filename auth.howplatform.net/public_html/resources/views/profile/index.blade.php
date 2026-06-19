{{-- resources/views/profile/index.blade.php --}}
@extends('layouts.app')
@section('title','الملف الشخصي')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
<link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endpush

@section('content')

<div class="profile-wrapper">
  <div class="profile-main-container">
    
    {{-- Profile Banner Header --}}
    <div class="profile-banner">
      <div class="profile-banner-content">
        <div class="profile-avatar-container">
          <img src="{{ $avatarUrl }}" alt="Avatar" class="profile-avatar-main" id="profileAvatarMain">
          <div class="avatar-edit-badge" id="avatarEditBadge">
            <i class="fa fa-camera"></i>
          </div>
        </div>
        
        <div class="profile-info-main">
          <h1 class="profile-name">{{ $user->name }}</h1>
          <div class="profile-email">{{ $user->email }}</div>
          <div class="profile-status-badges">
            @if($isVerified)
              <span class="status-badge">
                <i class="fa fa-check-circle"></i>
                <span>البريد مُفعّل</span>
              </span>
            @else
              <span class="status-badge">
                <i class="fa fa-exclamation-triangle"></i>
                <span>البريد غير مُفعّل</span>
              </span>
              <form action="{{ route('verification.send') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="status-badge" style="cursor: pointer; border: 2px solid rgba(255,255,255,.4);">
                  <i class="fa fa-paper-plane"></i>
                  <span>إعادة إرسال</span>
                </button>
              </form>
            @endif
          </div>
        </div>
      </div>
    </div>

    {{-- Alerts --}}
    @if (session('ok'))
      <div class="profile-alert profile-alert-success auto-dismiss">
        <i class="fa fa-check-circle profile-alert-icon"></i>
        <div>{{ session('ok') }}</div>
      </div>
    @endif

    @if (session('status') === 'verification-link-sent')
      <div class="profile-alert profile-alert-success auto-dismiss">
        <i class="fa fa-check-circle profile-alert-icon"></i>
        <div>تم إرسال رابط التفعيل إلى بريدك الإلكتروني.</div>
      </div>
    @endif

    @if (session('ok_diploma'))
      <div class="profile-alert profile-alert-success auto-dismiss">
        <i class="fa fa-check-circle profile-alert-icon"></i>
        <div>{{ session('ok_diploma') }}</div>
      </div>
    @endif

    @if (session('err_diploma'))
      <div class="profile-alert profile-alert-danger auto-dismiss">
        <i class="fa fa-exclamation-circle profile-alert-icon"></i>
        <div>{{ session('err_diploma') }}</div>
      </div>
    @endif

    @if ($errors->diploma->any())
      <div class="profile-alert profile-alert-danger auto-dismiss">
        <i class="fa fa-exclamation-circle profile-alert-icon"></i>
        <div>
          <strong>تحقق من الأخطاء التالية:</strong>
          <ul style="margin: .75rem 0 0 0; padding-right: 1.5rem;">
            @foreach ($errors->diploma->all() as $msg)
              <li>{{ $msg }}</li>
            @endforeach
          </ul>
        </div>
      </div>
    @endif

    {{-- Auto Redirect Banner --}}
    @if(!empty($redirectCountdown) && !empty($safeRedirect))
      <div class="profile-alert profile-alert-info" id="auto-redirect-banner">
        <i class="fa fa-info-circle profile-alert-icon"></i>
        <div style="flex: 1;">
          <div>تم اكتمال الملف. سيتم تحويلك لمنصة الدبلوم خلال <strong id="redirectSec">{{ $redirectCountdown }}</strong> ثانية.</div>
          <div style="margin-top: 1rem; display: flex; gap: .75rem; flex-wrap: wrap;">
            <a href="{{ $safeRedirect }}" class="profile-btn profile-btn-primary profile-btn-sm">
              <i class="fa fa-arrow-right"></i> الذهاب الآن
            </a>
            <button type="button" class="profile-btn profile-btn-secondary profile-btn-sm" id="cancelRedirect">
              إلغاء التحويل
            </button>
          </div>
        </div>
      </div>
    @endif

    {{-- Sections Navigation Chips --}}
    <div class="profile-sections-nav">
      <a href="#" class="section-chip active" data-section="auth">
        <span class="chip-icon"><i class="fa fa-user-circle"></i></span>
        <span class="chip-text">معلومات الحساب</span>
      </a>
      <a href="#" class="section-chip" data-section="diploma">
        <span class="chip-icon"><i class="fa fa-graduation-cap"></i></span>
        <span class="chip-text">بيانات الدبلوم</span>
      </a>
      <a href="#" class="section-chip" data-section="security">
        <span class="chip-icon"><i class="fa fa-lock"></i></span>
        <span class="chip-text">الأمان</span>
      </a>
      <a href="#" class="section-chip" data-section="danger">
        <span class="chip-icon"><i class="fa fa-trash-alt"></i></span>
        <span class="chip-text">حذف الحساب</span>
      </a>
    </div>

    {{-- Content Area --}}
    <main class="profile-content-area">
        
        {{-- Auth Section --}}
        <section class="profile-section-card active" id="section-auth">
          <div class="section-card-header">
            <h3 class="section-card-title">
              <span class="section-card-title-icon">
                <i class="fa fa-user-circle"></i>
              </span>
              <span>معلومات الحساب</span>
            </h3>
            <div class="section-card-actions">
              <button class="profile-btn profile-btn-ghost profile-btn-sm js-edit" data-form="form-auth">
                <i class="fa fa-edit"></i> تعديل
              </button>
              <button class="profile-btn profile-btn-primary profile-btn-sm d-none js-save" data-form="form-auth">
                <i class="fa fa-save"></i> حفظ
              </button>
              <button class="profile-btn profile-btn-secondary profile-btn-sm d-none js-cancel" data-form="form-auth">
                <i class="fa fa-times"></i> إلغاء
              </button>
            </div>
          </div>
          <div class="section-card-body">
            <form id="form-auth" class="locked" method="POST" action="{{ route('profile.update') }}">
              @csrf @method('PATCH')
              <input type="hidden" name="avatar" id="avatarUrlHidden" value="{{ old('avatar', $user->avatar) }}">
              
              <div class="profile-form-grid">
                <div class="profile-form-group">
                  <label class="profile-form-label">الاسم الكامل</label>
                  <input type="text" class="profile-form-input js-lock" name="name" value="{{ old('name', $user->name) }}" readonly>
                  @error('name')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>
                
                <div class="profile-form-group">
                  <label class="profile-form-label">البريد الإلكتروني</label>
                  <input type="email" class="profile-form-input js-lock" name="email" value="{{ old('email', $user->email) }}" readonly>
                  @error('email')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>
              </div>
            </form>
          </div>
        </section>

        {{-- Diploma Section --}}
        <section class="profile-section-card" id="section-diploma">
          <div class="section-card-header">
            <h3 class="section-card-title">
              <span class="section-card-title-icon">
                <i class="fa fa-graduation-cap"></i>
              </span>
              <span>بيانات الدبلوم العالي</span>
            </h3>
            <div class="section-card-actions">
              <button class="profile-btn profile-btn-ghost profile-btn-sm js-edit" data-form="form-diploma">
                <i class="fa fa-edit"></i> تعديل
              </button>
              <button class="profile-btn profile-btn-primary profile-btn-sm d-none js-save" data-form="form-diploma">
                <i class="fa fa-save"></i> حفظ
              </button>
              <button class="profile-btn profile-btn-secondary profile-btn-sm d-none js-cancel" data-form="form-diploma">
                <i class="fa fa-times"></i> إلغاء
              </button>
            </div>
          </div>
          <div class="section-card-body">
            <form id="form-diploma" class="locked" method="POST" action="{{ route('profile.diploma.update') }}">
              @csrf @method('PUT')
              
              <div class="profile-form-grid">
                <div class="profile-form-group">
                  <label class="profile-form-label">رقم الهاتف</label>
                  <input type="text" class="profile-form-input js-lock" name="phone_number" value="{{ old('phone_number', $du->phone_number) }}" readonly>
                  @error('phone_number','diploma')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>

                <div class="profile-form-group">
                  <label class="profile-form-label">الجامعة</label>
                  <select class="profile-form-select js-lock" name="university_id" id="university-select" disabled>
                    <option value="">— اختر —</option>
                    @foreach($universities as $u)
                      <option value="{{ $u->id }}" @selected(old('university_id', $du->university_id)==$u->id)>{{ $u->name }}</option>
                    @endforeach
                  </select>
                  @error('university_id','diploma')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>

                <div class="profile-form-group">
                  <label class="profile-form-label">التخصص</label>
                  <select class="profile-form-select js-lock" name="specialty_id" id="specialty-select" disabled>
                    <option value="">— اختر —</option>
                    @foreach($specialties as $s)
                      <option value="{{ $s->id }}" @selected(old('specialty_id', $du->specialty_id)==$s->id)>{{ $s->name }}</option>
                    @endforeach
                  </select>
                  @error('specialty_id','diploma')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>

                <div class="profile-form-group">
                  <label class="profile-form-label">نوع الدبلوم</label>
                  <select class="profile-form-select js-lock" name="diploma_type_id" disabled>
                    <option value="">— اختر —</option>
                    @foreach($diplomaTypes as $t)
                      <option value="{{ $t->id }}" @selected(old('diploma_type_id', $du->diploma_type_id)==$t->id)>{{ $t->name }}</option>
                    @endforeach
                  </select>
                  @error('diploma_type_id','diploma')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>

                <div class="profile-form-group">
                  <label class="profile-form-label">السنة الدراسية</label>
                  <select class="profile-form-select js-lock" name="academic_year_id" disabled>
                    <option value="">— اختر —</option>
                    @foreach($years as $y)
                      <option value="{{ $y->id }}" @selected(old('academic_year_id', $du->academic_year_id)==$y->id)>{{ $y->label }}</option>
                    @endforeach
                  </select>
                  @error('academic_year_id','diploma')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>

                <div class="profile-form-group">
                  <label class="profile-form-label">الجنس</label>
                  <select class="profile-form-select js-lock" name="gender" disabled>
                    <option value="">— اختر —</option>
                    <option value="male" @selected(old('gender', $du->gender)=='male')>ذكر</option>
                    <option value="female" @selected(old('gender', $du->gender)=='female')>أنثى</option>
                  </select>
                  @error('gender','diploma')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>

                <div class="profile-form-group">
                  <label class="profile-form-label">تاريخ الميلاد</label>
                  <input type="date" class="profile-form-input js-lock" name="birth_date" value="{{ old('birth_date', $du->birth_date) }}" readonly>
                  @error('birth_date','diploma')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>
              </div>
            </form>
          </div>
        </section>

        {{-- Security Section --}}
        <section class="profile-section-card" id="section-security">
          <div class="section-card-header">
            <h3 class="section-card-title">
              <span class="section-card-title-icon">
                <i class="fa fa-lock"></i>
              </span>
              <span>الأمان وكلمة المرور</span>
            </h3>
            <div class="section-card-actions">
              <button class="profile-btn profile-btn-ghost profile-btn-sm js-edit" data-form="form-security">
                <i class="fa fa-edit"></i> تعديل
              </button>
              <button class="profile-btn profile-btn-primary profile-btn-sm d-none js-save" data-form="form-security">
                <i class="fa fa-save"></i> حفظ
              </button>
              <button class="profile-btn profile-btn-secondary profile-btn-sm d-none js-cancel" data-form="form-security">
                <i class="fa fa-times"></i> إلغاء
              </button>
            </div>
          </div>
          <div class="section-card-body">
            <form id="form-security" class="locked" method="POST" action="{{ route('profile.password.update') }}">
              @csrf @method('PUT')
              
              <div class="profile-form-grid">
                <div class="profile-form-group">
                  <label class="profile-form-label">كلمة المرور الحالية</label>
                  <input type="password" class="profile-form-input js-lock" name="current_password" placeholder="••••••••" readonly>
                  @error('current_password')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>
                
                <div class="profile-form-group">
                  <label class="profile-form-label">كلمة المرور الجديدة</label>
                  <input type="password" class="profile-form-input js-lock" name="password" placeholder="••••••••" readonly>
                  @error('password')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>
                
                <div class="profile-form-group">
                  <label class="profile-form-label">تأكيد كلمة المرور الجديدة</label>
                  <input type="password" class="profile-form-input js-lock" name="password_confirmation" placeholder="••••••••" readonly>
                </div>
              </div>
            </form>
          </div>
        </section>

        {{-- Danger Zone --}}
        <section class="profile-section-card danger-zone" id="section-danger">
          <div class="section-card-header">
            <h3 class="section-card-title">
              <span class="section-card-title-icon">
                <i class="fa fa-exclamation-triangle"></i>
              </span>
              <span>حذف الحساب</span>
            </h3>
          </div>
          <div class="section-card-body">
            <p style="color: var(--error-600); margin-bottom: 1.5rem; font-weight: 700; line-height: 1.8;">
              <i class="fa fa-exclamation-circle"></i>
              سيؤدي حذف الحساب إلى حذف بياناتك وإنهاء الجلسة. لا يمكن التراجع عن هذا الإجراء.
            </p>
            <form method="POST" action="{{ route('profile.destroy') }}" onsubmit="return confirm('تأكيد حذف الحساب نهائيًا؟')">
              @csrf @method('DELETE')
              <div class="profile-form-grid">
                <div class="profile-form-group">
                  <label class="profile-form-label">أدخل كلمة المرور للتأكيد</label>
                  <input type="password" name="password" class="profile-form-input" required>
                  @error('password','userDeletion')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>
              </div>
              <div style="margin-top: 1.5rem;">
                <button type="submit" class="profile-btn profile-btn-danger">
                  <i class="fa fa-trash-alt"></i> حذف نهائي
                </button>
              </div>
            </form>
          </div>
        </section>

      </main>
  </div>
</div>

{{-- Avatar Modal - Complete Redesign --}}
<div class="modal fade" id="avatarModal" tabindex="-1" aria-labelledby="avatarModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content avatar-modal-content">
      <div class="modal-header avatar-modal-header">
        <h5 class="modal-title avatar-modal-title" id="avatarModalLabel">
          <i class="fa fa-image"></i> تعديل الصورة الشخصية
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body avatar-modal-body">
        {{-- Upload Section --}}
        <div class="avatar-upload-section">
          <label class="avatar-upload-label">اختر صورة من جهازك</label>
          <div class="avatar-file-input-wrapper">
            <input type="file" accept="image/*" id="avatarInputFile" class="avatar-file-input">
            <div class="avatar-tool-buttons">
              <button type="button" class="avatar-tool-btn" id="rotateLeftBtn" title="دوران يسار">
                <i class="fa fa-rotate-left"></i>
              </button>
              <button type="button" class="avatar-tool-btn" id="rotateRightBtn" title="دوران يمين">
                <i class="fa fa-rotate-right"></i>
              </button>
              <button type="button" class="avatar-tool-btn" id="flipHBtn" title="قلب أفقي">
                <i class="fa fa-arrows-left-right"></i>
              </button>
              <button type="button" class="avatar-tool-btn" id="flipVBtn" title="قلب عمودي">
                <i class="fa fa-arrows-up-down"></i>
              </button>
            </div>
          </div>
        </div>

        {{-- Crop & Preview Section --}}
        <div class="avatar-crop-preview-wrapper">
          <div class="avatar-crop-area">
            <img id="cropImageMain" src="{{ $avatarUrl }}" alt="للقص">
          </div>
          <div class="avatar-preview-section">
            <label class="avatar-preview-label">المعاينة</label>
            <img id="cropPreviewMain" class="avatar-preview-box" src="{{ $avatarUrl }}" alt="معاينة">
          </div>
        </div>
      </div>
      <div class="modal-footer avatar-modal-footer">
        <p class="avatar-modal-note">
          <i class="fa fa-info-circle"></i> يتم الرفع فور الضغط على "تطبيق"
        </p>
        <div class="avatar-modal-actions">
          <button type="button" class="profile-btn profile-btn-secondary" data-bs-dismiss="modal">
            <i class="fa fa-times"></i> إلغاء
          </button>
          <button type="button" class="profile-btn profile-btn-primary" id="applyCropBtn">
            <i class="fa fa-check"></i> تطبيق
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
  const $ = s => document.querySelector(s);
  const $$ = s => document.querySelectorAll(s);
  const CSRF = document.querySelector('meta[name="csrf-token"]').content;

  // ===== Section Navigation =====
  const ACTIVE_KEY = 'hp:profile:active';
  
  function activateSection(key) {
    $$('.profile-section-card').forEach(s => s.classList.remove('active'));
    const sec = document.getElementById('section-' + key);
    if (sec) sec.classList.add('active');

    $$('.section-chip').forEach(chip => {
      if (chip.dataset.section === key) {
        chip.classList.add('active');
      } else {
        chip.classList.remove('active');
      }
    });

    try {
      localStorage.setItem(ACTIVE_KEY, key);
    } catch (e) {}
  }

  document.addEventListener('click', (e) => {
    const chip = e.target.closest('[data-section]');
    if (!chip) return;
    e.preventDefault();
    activateSection(chip.dataset.section);
  });

  // ===== Form Lock/Unlock =====
  function lockForm(id, lock = true) {
    const form = document.getElementById(id);
    if (!form) return;
    
    const inputs = form.querySelectorAll('.js-lock');
    const card = form.closest('.profile-section-card');
    const btnSave = card?.querySelector('.js-save');
    const btnCancel = card?.querySelector('.js-cancel');
    const btnEdit = card?.querySelector('.js-edit');

    if (lock) {
      form.classList.add('locked');
      inputs.forEach(el => {
        if (el.tagName === 'SELECT') {
          el.disabled = true;
        } else {
          el.readOnly = true;
        }
      });
      btnSave?.classList.add('d-none');
      btnCancel?.classList.add('d-none');
      btnEdit?.classList.remove('d-none');
    } else {
      form.classList.remove('locked');
      inputs.forEach(el => {
        if (el.tagName === 'SELECT') {
          el.disabled = false;
        } else {
          el.readOnly = false;
        }
      });
      btnSave?.classList.remove('d-none');
      btnCancel?.classList.remove('d-none');
      btnEdit?.classList.add('d-none');
    }
  }

  document.addEventListener('click', (e) => {
    const edit = e.target.closest('.js-edit');
    if (edit) {
      lockForm(edit.dataset.form, false);
      return;
    }

    const cancel = e.target.closest('.js-cancel');
    if (cancel) {
      e.preventDefault();
      const form = document.getElementById(cancel.dataset.form);
      if (form) {
        form.reset();
        lockForm(cancel.dataset.form, true);
      }
      return;
    }

    const save = e.target.closest('.js-save');
    if (save) {
      e.preventDefault();
      document.getElementById(save.dataset.form)?.submit();
      return;
    }
  });

  // ===== Avatar Cropper =====
  let cropper = null;
  let scaleX = 1, scaleY = 1;
  const avatarImg = $('#profileAvatarMain');
  const avatarUrlInForm = $('#avatarUrlHidden');
  const modalEl = $('#avatarModal');
  const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
  const cropImage = $('#cropImageMain');
  const preview = $('#cropPreviewMain');
  const fileInput = $('#avatarInputFile');
  const btnApply = $('#applyCropBtn');

  function initCropper() {
    if (!cropImage) return;
    if (btnApply) btnApply.disabled = true;
    if (cropper) {
      try {
        cropper.destroy();
      } catch (_) {}
      cropper = null;
    }
    cropper = new Cropper(cropImage, {
      aspectRatio: 1,
      viewMode: 2,
      background: false,
      autoCropArea: 1,
      ready() {
        updatePreview();
        if (btnApply) btnApply.disabled = false;
      },
      crop() {
        updatePreview();
      }
    });
    scaleX = 1;
    scaleY = 1;
  }

  function updatePreview() {
    if (!cropper || !preview) return;
    try {
      const canvas = cropper.getCroppedCanvas({ width: 512, height: 512 });
      if (canvas) preview.src = canvas.toDataURL('image/webp', 0.95);
    } catch (_) {}
  }

  function openCropper() {
    if (!modal || !cropImage) return;
    cropImage.src = avatarImg ? avatarImg.src : '';
    modal.show();
  }

  avatarImg?.addEventListener('click', openCropper);
  $('#avatarEditBadge')?.addEventListener('click', openCropper);

  if (modalEl) {
    modalEl.addEventListener('shown.bs.modal', () => {
      if (cropImage.complete) {
        initCropper();
      } else {
        cropImage.onload = () => initCropper();
      }
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
      if (cropper) {
        try {
          cropper.destroy();
        } catch (_) {}
        cropper = null;
      }
      if (fileInput) fileInput.value = '';
    });
  }

  fileInput?.addEventListener('change', (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
      alert('الملف المختار ليس صورة');
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      alert('الحجم الأقصى 5MB');
      return;
    }

    const reader = new FileReader();
    reader.onload = () => {
      const dataUrl = reader.result;
      if (cropper) {
        cropper.replace(dataUrl);
      } else {
        cropImage.src = dataUrl;
        initCropper();
      }
      if (preview) preview.src = dataUrl;
    };
    reader.readAsDataURL(file);
  });

  $('#rotateLeftBtn')?.addEventListener('click', (e) => {
    e.preventDefault();
    cropper?.rotate(-90);
  });

  $('#rotateRightBtn')?.addEventListener('click', (e) => {
    e.preventDefault();
    cropper?.rotate(90);
  });

  $('#flipHBtn')?.addEventListener('click', (e) => {
    e.preventDefault();
    if (cropper) {
      scaleX *= -1;
      cropper.scaleX(scaleX);
    }
  });

  $('#flipVBtn')?.addEventListener('click', (e) => {
    e.preventDefault();
    if (cropper) {
      scaleY *= -1;
      cropper.scaleY(scaleY);
    }
  });

  btnApply?.addEventListener('click', () => {
    if (!cropper) return;

    const src = cropImage?.src || '';
    const isData = src.startsWith('data:');
    const isSame = src.startsWith(location.origin + '/') || src === '' || isData;
    if (!isSame) {
      alert('لا يمكن قص صورة خارجية. الرجاء اختيار صورة من جهازك.');
      return;
    }

    let canvas;
    try {
      canvas = cropper.getCroppedCanvas({ width: 512, height: 512 });
    } catch (_) {
      alert('تعذّر تجهيز الصورة.');
      return;
    }
    if (!canvas) return;

    const original = btnApply.innerHTML;
    btnApply.disabled = true;
    btnApply.innerHTML = '<i class="fa fa-spinner fa-spin"></i> جاري الرفع...';

    canvas.toBlob(async (blob) => {
      if (!blob) {
        btnApply.disabled = false;
        btnApply.innerHTML = original;
        alert('تعذّر تجهيز الصورة.');
        return;
      }
      try {
        const fd = new FormData();
        fd.append('avatar', blob, 'avatar.webp');

        const res = await fetch("{{ route('profile.avatar.upload') }}", {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': CSRF },
          body: fd
        });
        const json = await res.json();
        if (!res.ok || !json.success) {
          throw new Error(json.message || 'فشل الرفع');
        }

        if (avatarImg && json.url) avatarImg.src = json.url;
        if (avatarUrlInForm && json.url) avatarUrlInForm.value = json.url;
        modal?.hide();
      } catch (err) {
        alert(err.message || 'تعذّر رفع الصورة');
      } finally {
        btnApply.disabled = false;
        btnApply.innerHTML = original;
      }
    }, 'image/webp', 0.95);
  });

  // ===== Auto Redirect Countdown =====
  @if(!empty($redirectCountdown) && !empty($safeRedirect))
    (function() {
      let sec = {{ (int)$redirectCountdown }};
      const url = @json($safeRedirect);
      let cancelled = false;
      const secEl = $('#redirectSec');
      const cancelBtn = $('#cancelRedirect');
      const banner = $('#auto-redirect-banner');

      if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
          cancelled = true;
          if (banner) {
            banner.classList.remove('profile-alert-info');
            banner.classList.add('profile-alert-warning');
            const contentWrapper = banner.querySelector('div[style*="flex: 1"]');
            if (contentWrapper) {
              const textDiv = contentWrapper.querySelector('div:first-child');
              const buttonsDiv = contentWrapper.querySelector('div:last-child');
              if (textDiv) {
                textDiv.innerHTML = 'تم إلغاء التحويل التلقائي. يمكنك المتابعة هنا أو الضغط على "الذهاب الآن".';
              }
              // إخفاء زر الإلغاء بعد الضغط عليه
              if (cancelBtn) {
                cancelBtn.style.display = 'none';
              }
            }
          }
        });
      }

      function tick() {
        if (cancelled) return;
        if (sec <= 0) {
          window.location.assign(url);
          return;
        }
        sec--;
        if (secEl) secEl.textContent = sec;
        setTimeout(tick, 1000);
      }
      setTimeout(tick, 1000);
    })();
  @endif

  // ===== Load Specialties by University =====
  const universitySelect = $('#university-select');
  const specialtySelect = $('#specialty-select');
  
  if (universitySelect && specialtySelect) {
    universitySelect.addEventListener('change', async function() {
      const universityId = this.value;
      
      // إعادة تعيين التخصص
      specialtySelect.innerHTML = '<option value="">— اختر —</option>';
      specialtySelect.disabled = true;
      
      if (!universityId) {
        return;
      }
      
      try {
        specialtySelect.disabled = true;
        specialtySelect.innerHTML = '<option value="">جاري التحميل...</option>';
        
        const res = await fetch(`{{ route('profile.specialties.by-university') }}?university_id=${universityId}`, {
          method: 'GET',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': CSRF
          }
        });
        
        const json = await res.json();
        
        if (!res.ok || !json.success) {
          throw new Error(json.message || 'فشل جلب التخصصات');
        }
        
        specialtySelect.innerHTML = '<option value="">— اختر —</option>';
        
        if (json.specialties && json.specialties.length > 0) {
          json.specialties.forEach(specialty => {
            const option = document.createElement('option');
            option.value = specialty.id;
            option.textContent = specialty.name;
            specialtySelect.appendChild(option);
          });
        }
        
        specialtySelect.disabled = false;
      } catch (err) {
        console.error('خطأ في جلب التخصصات:', err);
        specialtySelect.innerHTML = '<option value="">— خطأ في التحميل —</option>';
        alert('تعذّر جلب التخصصات. يرجى المحاولة مرة أخرى.');
      }
    });
  }

  // ===== Initialize =====
  document.addEventListener('DOMContentLoaded', () => {
    const initial = '{{ session('active', 'auth') }}';
    activateSection(initial);

    ['form-auth', 'form-diploma', 'form-security'].forEach(id => {
      lockForm(id, true);
    });

    // تفعيل select الجامعة عند فتح النموذج للتعديل
    const diplomaForm = $('#form-diploma');
    if (diplomaForm) {
      const unlockBtn = diplomaForm.querySelector('.unlock-btn');
      if (unlockBtn) {
        unlockBtn.addEventListener('click', () => {
          if (universitySelect && !universitySelect.disabled) {
            // إذا كانت هناك جامعة مختارة، جلب التخصصات المرتبطة بها
            if (universitySelect.value) {
              universitySelect.dispatchEvent(new Event('change'));
            }
          }
        });
      }
    }

    setTimeout(() => {
      $$('.auto-dismiss').forEach(el => {
        el.style.transition = 'opacity 0.5s ease';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
      });
    }, 10000);
  });
</script>
@endpush
