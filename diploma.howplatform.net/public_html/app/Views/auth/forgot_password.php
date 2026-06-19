<?php
$success_message = $success_message ?? null;
$error_message = $error_message ?? null;
?>

<main class="login-main">
    <div class="login-container">
        <div class="login-card">
            <h2 class="login-title">استعادة كلمة المرور</h2>

            <?php if ($success_message): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php elseif ($error_message): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form action="<?= site_url('auth/forgot-password') ?>" method="post" class="login-form">
                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope"></i> البريد الإلكتروني:
                    </label>
                    <input type="email" name="email" id="email" class="form-input" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary login-btn">
                        <i class="fas fa-paper-plane"></i> إرسال رابط الاستعادة
                    </button>
                </div>
            </form>

            <div class="login-links">
                <p><a  href="<?= site_url('auth/login') ?>" class="forgot-link">العودة لتسجيل الدخول</a></p>
            </div>
        </div>

        <div class="login-illustration">
            <div class="hero-illustration">
                <div class="hero-illustration-inner">
                    <div class="edu-icons">
                        <div class="edu-icon icon-1">
                            <i class="fas fa-envelope-open-text"></i>
                        </div>
                        <div class="edu-icon icon-2">
                            <i class="fas fa-unlock-alt"></i>
                        </div>
                        <div class="edu-icon icon-3">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
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
    background: rgba(0,200,83,0.1);
    color: #2e7d32;
    padding: 1rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    border-left: 4px solid #2e7d32;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: fadeIn 0.5s ease;
    line-height: 1.5rem;
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
        .login-container {
            flex-direction: column;
            gap: 3rem;
        }
        
        .login-card {
            max-width: 600px;
            width: 100%;
        }
        
        .login-illustration {
            max-width: 100%;
        }
    }
    
    @media (max-width: 576px) {
        .login-card {
            padding: 1.8rem;
        }
        
        .login-title {
            font-size: 1.8rem;
        }
    }
</style>