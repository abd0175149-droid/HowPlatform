<style>
    /* ستايل صفحة تسجيل مستخدم جديد */
    .register-main {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 80vh;
        padding: 2rem 5%;
        background: radial-gradient(circle at 10% 20%, rgba(142, 202, 230, 0.1) 0%, rgba(255, 255, 252, 0) 50%);
    }
    
    .register-container {
        display: flex;
        gap: 4rem;
        width: 100%;
        max-width: 1200px;
        align-items: center;
    }
    
    .register-card {
        flex: 1;
        background: var(--background);
        border-radius: 20px;
        padding: 2.5rem;
        box-shadow: 0 15px 30px rgba(2, 48, 71, 0.1);
        border: 1px solid rgba(142, 202, 230, 0.3);
        animation: fadeIn 0.8s ease;
        max-width: 500px;
    }
    
    .register-title {
        font-size: 2.2rem;
        margin-bottom: 1.8rem;
        color: var(--primary-darker);
        text-align: center;
        position: relative;
        padding-bottom: 1rem;
    }
    
    .register-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 150px;
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
        position: relative;
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
    
    .password-hint {
        display: block;
        margin-top: 0.5rem;
        color: var(--primary);
        font-size: 0.9rem;
        opacity: 0.8;
    }
    
    .form-actions {
        margin-top: 2.5rem;
        text-align: center;
    }
    
    .register-btn {
        padding: 1rem 3rem;
        font-size: 1.2rem;
        border-radius: 12px;
        transition: all 0.3s ease;
        width: 100%;
        background: linear-gradient(135deg, var(--accent-light), var(--accent));
        color: var(--primary-darker);
        justify-content: center;
    }
    
    .register-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 7px 20px rgba(255, 183, 3, 0.4);
    }
    
    .register-links {
        margin-top: 2rem;
        text-align: center;
    }
    
    .register-links a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 700;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .register-links a:hover {
        color: var(--primary-darker);
    }
    
    .register-links a::after {
        content: '';
        position: absolute;
        bottom: -2px;
        right: 0;
        width: 0;
        height: 2px;
        background: var(--accent);
        transition: width 0.3s ease;
    }
    
    .register-links a:hover::after {
        width: 100%;
        left: 0;
    }
    
    .register-illustration {
        flex: 1;
        max-width: 500px;
        animation: slideInRight 0.8s ease;
    }
    
    .hero-illustration {
        background: linear-gradient(135deg, var(--primary-light), var(--primary));
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
        .register-container {
            flex-direction: column;
            gap: 3rem;
        }
        
        .register-card {
            max-width: 600px;
            width: 100%;
        }
        
        .register-illustration {
            max-width: 100%;
        }
    }
    
    @media (max-width: 576px) {
        .register-card {
            padding: 1.8rem;
        }
        
        .register-title {
            font-size: 1.8rem;
        }
        
        .register-title::after {
            width: 120px;
        }
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
        color: var(--primary-dark);
    }
</style>

<main class="register-main">
    <div class="register-container">
        <div class="register-card">
            <h2 class="register-title">إعادة تعيين كلمة المرور</h2>
                <div id="reset-msg"></div>
            <?php if (isset($_GET['message'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['message']); ?>
                </div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>
            <form id="resetForm" class="register-form">
                <input type="hidden" name="token" value="<?php echo $token; ?>">
                <div class="form-group">
                    <label for="new_password" class="form-label">
                        <i class="fas fa-lock"></i> كلمة المرور الجديدة:
                    </label>
                    <div class="password-wrapper">
                        <input type="password" name="new_password" id="new_password" class="form-input" required>
                        <i class="fas fa-eye toggle-password" data-target="new_password"></i>
                    </div>
                    <small class="password-hint"> يجب أن تتكون كلمة المرور من 8 خانات على الأقل، وتحتوي على حرف واحد، رقم واحد، ورمز خاص على الأقل.</small>
                </div>
                <div class="form-group">
                    <label for="confirm_password" class="form-label">
                        <i class="fas fa-lock"></i> تأكيد كلمة المرور:
                    </label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-input" required>
                        <i class="fas fa-eye toggle-password" data-target="confirm_password"></i>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary register-btn">
                        <i class="fas fa-sync-alt"></i> تأكيد التعيين
                    </button>
                </div>
            </form>
            <div class="register-links">
                <p>هل تتذكر كلمة المرور؟ <a  href="<?= site_url('auth/login') ?>" class="login-link">تسجيل الدخول</a></p>
            </div>
        </div>

        <div class="register-illustration">
            <div class="hero-illustration">
                <div class="hero-illustration-inner">
                    <div class="edu-icons">
                        <div class="edu-icon icon-1"><i class="fas fa-unlock"></i></div>
                        <div class="edu-icon icon-2"><i class="fas fa-key"></i></div>
                        <div class="edu-icon icon-3"><i class="fas fa-shield-alt"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
document.getElementById("resetForm").addEventListener("submit", function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch("<?= site_url('auth/reset-password') ?>", {
        method: "POST",
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': "<?= csrf_hash() ?>"
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        const msg = document.getElementById("reset-msg");
        msg.innerHTML = ''; // مسح الرسائل السابقة

        if (data.success) {
            msg.innerHTML = `<div class="success-message"><i class="fas fa-check-circle"></i> ${data.message}</div>`;
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 2000);
        } else {
            msg.innerHTML = `<div class="error-message"><i class="fas fa-exclamation-circle"></i> ${data.message}</div>`;
        }
    })
    .catch(error => {
        console.error("Error resetting password:", error);
        document.getElementById("reset-msg").innerHTML = 
            `<div class="error-message"><i class="fas fa-exclamation-circle"></i> حدث خطأ أثناء الإرسال.</div>`;
    });
});

// Toggle password visibility
document.querySelectorAll(".toggle-password").forEach(icon => {
    icon.addEventListener("click", () => {
        const targetId = icon.getAttribute("data-target");
        const input = document.getElementById(targetId);

        if (input.type === "password") {
            input.type = "text";
            icon.classList.replace("fa-eye", "fa-eye-slash");
        } else {
            input.type = "password";
            icon.classList.replace("fa-eye-slash", "fa-eye");
        }
    });
});
</script>
