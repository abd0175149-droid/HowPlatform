<main class="login-main">
    <div class="login-container">
        <div class="login-card">
            <h2 class="login-title">تسجيل الدخول</h2>
            <div id="msg-area">
                <?php if (service('request')->getGet('message')): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <?= esc(service('request')->getGet('message')) ?>
                    </div>
                <?php elseif (service('request')->getGet('error')): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= esc(service('request')->getGet('error')) ?>
                    </div>
                <?php endif; ?>
            </div>
            <form id="loginForm" class="login-form">
                <div class="form-actions">
                    <a href="<?= site_url('auth/google') ?>" class="btn btn-google">
                        <img src="<?= base_url('assets/images/google-icon.svg') ?>" alt="Google Icon" style="width:20px; vertical-align:middle; margin-left:8px;">
                        تسجيل الدخول باستخدام Google
                    </a>
                    <a href="https://auth.howplatform.net/login?redirect=https://diploma.howplatform.net/students" style="display:none">
                           دخول حساب جديد
                        </a>
                </div>
                <div class="divider"><span>أو</span></div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-envelope"></i> البريد الإلكتروني:</label>
                    <input type="email" name="email" id="email" class="form-input" required value="<?= esc($email ?? '') ?>">

                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-lock"></i> كلمة المرور:</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" class="form-input" required>
                        <i class="fas fa-eye toggle-password" data-target="password"></i>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary login-btn">
                        <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
                    </button>
                </div>
                <br>
                <div class="form-group"><label class="form-label">
                <input type="checkbox" name="remember_email" <?= isset($email) ? 'checked' : '' ?>>
                تذكر بريدي الإلكتروني
              </label>
              </div>
            </form>
            <div class="login-links">
                <p>ليس لديك حساب؟ <a href="<?= site_url('auth/register') ?>">سجّل الآن</a></p>
                <p><a href="<?= site_url('auth/forgot-password') ?>">نسيت كلمة المرور؟</a></p>
            </div>
        </div>
        <div class="login-illustration">
            <div class="hero-illustration">
                <div class="hero-illustration-inner">
                    <div class="edu-icons">
                        <div class="edu-icon icon-1">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="edu-icon icon-2">
                            <i class="fas fa-key"></i>
                        </div>
                        <div class="edu-icon icon-3">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- JS مضمَّن أسفل الصفحة -->
<script>
document.addEventListener('DOMContentLoaded', () => {
 const LOGIN_URL = "<?= site_url('auth/login') ?>";

    /*----------------------------------------------------
      إرسال النموذج عبر Fetch + CSRF
    ----------------------------------------------------*/
    document.getElementById('loginForm').addEventListener('submit', e => {
        e.preventDefault();

        const formData = new FormData(e.target);

        fetch(LOGIN_URL, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN'    : "<?= csrf_hash() ?>"
            },
            body: formData
        })
        .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
        .then(data => {
            const msg = document.getElementById('msg-area');
            msg.innerHTML = ''; // تفريغ أي رسائل سابقة
        
            // ✅ فقط إذا كانت هناك رسالة، يتم عرضها
            if (data.message) {
                msg.innerHTML = `
                  <div class="${data.success ? 'success-message' : 'error-message'}">
                      <i class="fas ${data.success ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                      ${data.message}
                  </div>`;
            }
        
            // ✅ في حال نجاح الدخول، تنفيذ التوجيه
            if (data.success && data.redirect) {
                window.location.href = data.redirect;
            }
        })

        .catch(err => {
            console.error(err);
            document.getElementById('msg-area').innerHTML =
              '<div class="error-message"><i class="fas fa-exclamation-circle"></i> حدث خطأ في الاتصال بالخادم.</div>';
        });
    });


    // إظهار/إخفاء كلمة المرور
    document.querySelectorAll('.toggle-password').forEach(icon => {
        icon.addEventListener('click', () => {
            const input = document.getElementById(icon.dataset.target);
            const show  = input.type === 'password';
            input.type  = show ? 'text' : 'password';
            icon.classList.toggle('fa-eye',  !show);
            icon.classList.toggle('fa-eye-slash', show);
        });
    });
});
</script>

<style>
.btn-google {
    position: relative;
    background-color: #ffffff;
    border: 2px solid #ddd;
    color: #444;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    overflow: hidden;
    border-radius: 12px;
    transition: all 0.3s ease-in-out;
    cursor: pointer;
    z-index: 1;
}

.btn-google:hover {
    border: 2px solid transparent;
    border-radius: 12px;
    background-image:
        linear-gradient(#fff, #fff),
        linear-gradient(45deg, #4285F4, #EA4335, #FBBC05, #34A853);
    background-origin: border-box;
    background-clip: padding-box, border-box;
    animation: none;
    box-shadow:
        0 0 4px rgba(66, 133, 244, 0.2),
        0 0 8px rgba(234, 67, 53, 0.2),
        0 0 12px rgba(251, 188, 5, 0.15),
        0 0 16px rgba(52, 168, 83, 0.1);
}

.divider {
    display: flex;
    align-items: center;
    text-align: center;
    margin: 2rem 0;
    color: #888;
    font-weight: bold;
    font-size: 1rem;
}

.divider::before,
.divider::after {
    content: "";
    flex: 1;
    border-bottom: 1px solid #ccc;
}

.divider:not(:empty)::before {
    margin-left: 1rem;
}

.divider:not(:empty)::after {
    margin-right: 1rem;
}

    /* ستايل صفحة تسجيل الدخول */
    .login-main {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 80vh;
        padding: 2rem 5%;
        background: radial-gradient(circle at 90% 20%, rgba(142, 202, 230, 0.1) 0%, rgba(255, 255, 252, 0) 50%);
    }
    
    .login-container {
        display: flex;
        gap: 4rem;
        width: 100%; 
        max-width: 1200px;
        align-items: center;
    }
    
    .login-card {
        flex: 1;
        background: var(--background);
        border-radius: 20px;
        padding: 2.5rem;
        box-shadow: 0 15px 30px rgba(2, 48, 71, 0.1);
        border: 1px solid rgba(142, 202, 230, 0.3);
        animation: fadeIn 0.8s ease;
        max-width: 500px;
    }
    
    .login-title {
        font-size: 2.2rem;
        margin-bottom: 1.8rem;
        color: var(--primary-darker);
        text-align: center;
        position: relative;
        padding-bottom: 1rem;
    }
    
    .login-title::after {
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
.login-illustration { flex: 1; max-width: 500px; animation: slideInRight 0.8s ease; }
.hero-illustration { background: linear-gradient(135deg, var(--primary-light), var(--primary)); }
.hero-illustration-inner { background-color: var(--background); height: 350px; }
.edu-icons { display: flex; justify-content: center; align-items: center; height: 100%; gap: 40px; flex-wrap: wrap; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideInRight { from { opacity: 0; transform: translateX(50px); } to { opacity: 1; transform: translateX(0); } }
/* Responsive */
@media (max-width: 992px) {
    .login-container { flex-direction: column; gap: 3rem; }
    .login-card { max-width: 600px; width: 100%; }
    .login-illustration { max-width: 100%; }
}
@media (max-width: 576px) {
    .login-card { padding: 1.8rem; }
    .login-title { font-size: 1.8rem; }
    .login-title::after { width: 120px; }
}
    .password-wrapper {
    position: relative;
    }
    
    .password-wrapper input {
        padding-right: 45px;
    }
    
    .password-wrapper .toggle-password {
        position: absolute;
        top: 50%;
        right: 15px;
        transform: translateY(-50%);
        color: #888;
        cursor: pointer;
        font-size: 1.1rem;
    }
    .password-wrapper .toggle-password:hover {
       
</style>
<script>
     localStorage.clear();
</script>