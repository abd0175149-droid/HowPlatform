<div class="content">
    <div class="hero-text">
        <h1>مرحبًا بكم في كيف؟ التعليمية</h1>
        <p>منصة تعليمية حديثة تقدم الدعم الفني والأكاديمي للمعلمين بسهولة وأمان. انضم إلينا اليوم وابدأ رحلتك التعليمية.</p>
    </div>
    <div class="cta-buttons">
        <a href="<?= site_url('auth/redirect') ?>" class="btn btn-primary">
            <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
        </a>
        <a href="https://auth.howplatform.net/register?redirect=https://diploma.howplatform.net/auth/redirect" class="btn btn-secondary">
            <i class="fas fa-user-plus"></i> إنشاء حساب جديد
        </a>
    </div>
</div>

<div class="hero-image">
    <div class="hero-illustration">
        <div class="hero-illustration-inner">
            <div class="edu-icons">
                <div class="edu-icon icon-1"><i class="fas fa-laptop"></i></div>
                <div class="edu-icon icon-2"><i class="fas fa-book"></i></div>
                <div class="edu-icon icon-3"><i class="fas fa-users"></i></div>
                <div class="edu-icon icon-4"><i class="fas fa-chalkboard-teacher"></i></div>
            </div>
        </div>
    </div>
</div>
<script>
    document.querySelectorAll('.btn').forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
        });
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animation = 'fadeIn 0.5s ease forwards';
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.edu-icon').forEach(icon => {
        observer.observe(icon);
    });
</script>
<style>
    .register-illustration { flex: 1; max-width: 500px; animation: slideInRight 0.8s ease; }
.hero-illustration { background: linear-gradient(135deg, var(--primary-light), var(--primary)); }
.hero-illustration-inner { background-color: var(--background); height: 350px; }
.edu-icons { display: flex; justify-content: center; align-items: center; height: 100%; gap: 40px; flex-wrap: wrap; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideInRight { from { opacity: 0; transform: translateX(50px); } to { opacity: 1; transform: translateX(0); } }
/* Responsive */
@media (max-width: 992px) {
    .register-container { flex-direction: column; gap: 3rem; }
    .register-card { max-width: 600px; width: 100%; }
    .register-illustration { max-width: 100%; }
}
@media (max-width: 576px) {
    .register-card { padding: 1.8rem; }
    .register-title { font-size: 1.8rem; }
    .register-title::after { width: 120px; }
}
</style>
