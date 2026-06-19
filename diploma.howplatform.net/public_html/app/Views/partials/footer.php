    </main>
<div id="cookie-banner">
    <div class="cookie-content">
        <div class="cookie-icon">
            <i class="fas fa-cookie-bite"></i>
        </div>
        
        <div class="cookie-text">
            <p>
                نستخدم ملفات تعريف الارتباط (كوكيز) لتحسين تجربتك على موقعنا. تمكننا ملفات تعريف الارتباط من تذكر 
                تفضيلاتك وتقديم محتوى أكثر تخصيصًا. بعض ملفات تعريف الارتباط ضرورية لعمل الموقع بشكل صحيح، بينما 
                تساعدنا أخرى في فهم كيفية استخدامك للموقع. يمكنك إدارة تفضيلاتك في أي وقت.
            </p>
            
            <p>
                بمواصلة تصفح الموقع، فإنك توافق على استخدامنا لملفات تعريف الارتباط وفقًا لـ 
                <a href="<?= site_url('auth/privacy') ?>" target="_blank">
                    <i class="fas fa-shield-alt"></i>
سياسة الخصوصية</a> و
                <a href="<?= site_url('auth/terms') ?>" target="_blank">
                                    <i class="fas fa-file-contract"></i>

                    الشروط والأحكام </a>.
            </p>
        </div>
    </div>
    
    <div class="cookie-buttons">
        <button id="accept-cookies">
            <i class="fas fa-check-circle"></i> أوافق
        </button>
        <button id="reject-cookies">
            <i class="fas fa-times-circle"></i> أرفض
        </button>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const banner = document.getElementById('cookie-banner');
    const acceptBtn = document.getElementById('accept-cookies');
    const rejectBtn = document.getElementById('reject-cookies');

    const cookieConsent = getCookie('cookie_consent');

    // ✅ 1. إظهار الشريط بعد 5 ثواني إذا لم يكن المستخدم اختار سابقًا
    if (!cookieConsent) {
        setTimeout(() => {
            banner.classList.add('show');
        }, 2000);

        // ✅ 2. إخفاؤه تلقائيًا بعد 35 ثانية
        setTimeout(() => {
            banner.classList.remove('show');
        }, 35000);
    }

    // ✅ 3. عند الضغط على "أوافق"
    acceptBtn.addEventListener('click', () => {
        setCookie('cookie_consent', 'accepted', 365);
        banner.classList.remove('show');
    });

    // ❌ 4. عند الضغط على "أرفض"
    rejectBtn.addEventListener('click', () => {
        setCookie('cookie_consent', 'rejected', 365);
        banner.classList.remove('show');
    });

    // أدوات مساعدة
    function setCookie(name, value, days) {
        const d = new Date();
        d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = `${name}=${value}; expires=${d.toUTCString()}; path=/`;
    }

    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? match[2] : null;
    }
});
</script>
<footer style="background-color: var(--primary); color: var(--background); padding: 2rem 5%; margin-top: 3rem;">
  <div class="footer-content" style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 2rem;">

    <!-- الشعار والوصف -->
    <div class="footer-logo" style="flex: 1 1 250px;">
      <h2 style="font-weight: 800; font-size: 1.5rem; margin-bottom: 1rem;">كيف؟ التعليمية</h2>
      <p style="font-size: 1rem;">منصة تعليمية رائدة تهدف إلى دعم المعلمين وتمكينهم بأحدث الأدوات والموارد التعليمية لتقديم تجربة تعليمية استثنائية.</p>
    </div>

    <!-- روابط مهمة -->
    <div class="footer-links" style="flex: 1 1 200px;">
      <h3 style="font-weight: 700; margin-bottom: 1rem;">روابط مهمة</h3>
      <ul style="list-style: none; padding: 0; margin: 0;">
        <li style="margin-bottom: .5rem;">
          <a href="<?= site_url('auth/privacy') ?>" style="color: var(--background); text-decoration: none; display: inline-flex; align-items:center; gap:.5rem;">
            <i class="fas fa-chevron-left" aria-hidden="true"></i><span>سياسة الخصوصية</span>
          </a>
        </li>
        <li style="margin-bottom: .5rem;">
          <a href="<?= site_url('auth/terms') ?>" style="color: var(--background); text-decoration: none; display: inline-flex; align-items:center; gap:.5rem;">
            <i class="fas fa-chevron-left" aria-hidden="true"></i><span>الشروط والأحكام</span>
          </a>
        </li>
        <li>
          <a href="<?= site_url('auth/faq') ?>" style="color: var(--background); text-decoration: none; display: inline-flex; align-items:center; gap:.5rem;">
            <i class="fas fa-chevron-left" aria-hidden="true"></i><span>الأسئلة الشائعة</span>
          </a>
        </li>
      </ul>
    </div>

    <!-- تواصل معنا -->
    <div class="footer-contact" style="flex: 1 1 240px;">
      <h3 style="font-weight: 700; margin-bottom: 1rem;">تواصل معنا</h3>

      <div class="contact-info" style="display: flex; align-items: center; gap: 10px; margin-bottom: .5rem;">
        <i class="fas fa-envelope" aria-hidden="true"></i>
        <!-- ✅ تصحيح mailto ليطابق النص -->
        <a href="mailto:support@howplatform.net" style="color: var(--background); text-decoration: none;">support@howplatform.net</a>
      </div>

      <div class="contact-info" style="display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-headset" aria-hidden="true"></i>
        <span>الدعم الفني: متاح من الساعة 9 صباحًا إلى 5 مساءً</span>
      </div>
    </div>

    <!-- تابعنا (السوشيال) -->
    <div class="footer-social" style="flex: 1 1 260px;">
      <h3 style="font-weight: 700; margin-bottom: 1rem;">تابعنا</h3>

      <div style="display:flex; flex-wrap: wrap; gap: .6rem;">
        <!-- Facebook -->
        <a class="social-link" aria-label="Facebook"
           href="https://www.facebook.com/people/%D9%85%D9%86%D8%B5%D8%A9-%D9%83%D9%8A%D9%81-%D8%A7%D9%84%D8%AA%D8%B9%D9%84%D9%8A%D9%85%D9%8A%D8%A9-%D8%A7%D9%84%D8%AF%D8%A8%D9%84%D9%88%D9%85-%D8%A7%D9%84%D8%B9%D8%A7%D9%84%D9%8A/61579341362175/"
           target="_blank" rel="noopener noreferrer"
           style="display:inline-flex; align-items:center; gap:.5rem; padding:.55rem .9rem; border:1px solid rgba(255,255,255,.35); border-radius:999px; color:var(--background); text-decoration:none; font-weight:700; transition:transform .2s ease, background-color .2s ease;">
          <i class="fab fa-facebook-f" aria-hidden="true"></i><span>Facebook</span>
        </a>

        <!-- Instagram -->
        <a class="social-link" aria-label="Instagram"
           href="https://www.instagram.com/diploma_how?utm_source=ig_web_button_share_sheet&igsh=Z2RyM3V1djF5cnlm"
           target="_blank" rel="noopener noreferrer"
           style="display:inline-flex; align-items:center; gap:.5rem; padding:.55rem .9rem; border:1px solid rgba(255,255,255,.35); border-radius:999px; color:var(--background); text-decoration:none; font-weight:700; transition:transform .2s ease, background-color .2s ease;">
          <i class="fab fa-instagram" aria-hidden="true"></i><span>Instagram</span>
        </a>

        <!-- Telegram -->
        <a class="social-link" aria-label="Telegram"
           href="https://t.me/howplatform_diploma"
           target="_blank" rel="noopener noreferrer"
           style="display:inline-flex; align-items:center; gap:.5rem; padding:.55rem .9rem; border:1px solid rgba(255,255,255,.35); border-radius:999px; color:var(--background); text-decoration:none; font-weight:700; transition:transform .2s ease, background-color .2s ease;">
          <i class="fab fa-telegram-plane" aria-hidden="true"></i><span>Telegram</span>
        </a>

        <!-- WhatsApp -->
        <a class="social-link" aria-label="WhatsApp"
           href="https://wa.me/962782550901"
           target="_blank" rel="noopener noreferrer"
           style="display:inline-flex; align-items:center; gap:.5rem; padding:.55rem .9rem; border:1px solid rgba(255,255,255,.35); border-radius:999px; color:var(--background); text-decoration:none; font-weight:700; transition:transform .2s ease, background-color .2s ease;">
          <i class="fab fa-whatsapp" aria-hidden="true"></i><span>WhatsApp</span>
        </a>
      </div>

      <!-- تلميح صغير -->
      <p style="margin-top:.75rem; font-size:.9rem; opacity:.9;">نسعد بتواصلك عبر أي قناة تناسبك.</p>
    </div>
    <img src="<?= base_url('assets/images/Logo_how_w.svg') ?>" width="150" height="30" style="opacity:1;position:relative; width:150px;" alt="كيف التعليمية">

  </div>

  <div class="copyright" style="margin-top: 2rem; text-align: center; font-size: 0.9rem; border-top: 1px solid rgba(255, 255, 255, 0.2); padding-top: 1rem;">
    <p>© 2025 howplatform.net - جميع الحقوق محفوظة</p>
  </div>
</footer>
<style>
#cookie-banner {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    background: var(--primary-darker);
    color: white;
    padding: 20px;
    text-align: center;
    z-index: 9999;
    box-shadow: 0 -5px 15px rgba(0, 0, 0, 0.2);
    transform: translateY(100%);
    transition: transform 0.5s ease;
}

#cookie-banner.show {
    transform: translateY(0);
    display: flex;
    flex-direction: column;
    align-items: center;
}

.cookie-content {
    max-width: 900px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 10px 0;
}

.cookie-icon {
    font-size: 2.5rem;
    color: var(--accent);
}

.cookie-text {
    flex: 1;
    text-align: right;
    line-height: 1.6;
}

.cookie-text a {
    color: var(--accent);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-block;
    margin: 0 5px;
}

.cookie-text a:hover {
    text-decoration: underline;
    transform: translateY(-2px);
}

.cookie-buttons {
    display: flex;
    gap: 15px;
    margin-top: 15px;
    flex-wrap: wrap;
    justify-content: center;
}

#accept-cookies, 
#reject-cookies,
#manage-cookies {
    padding: 12px 25px;
    border: none;
    border-radius: 30px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

#accept-cookies {
    background: var(--accent);
    color: var(--primary-darker);
}

#accept-cookies:hover {
    background: var(--accent-dark);
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(251, 133, 0, 0.3);
}

#reject-cookies {
    background: var(--rejected);
    color: white;
}

#reject-cookies:hover {
    background: var(--rejected-dark);
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
}

#manage-cookies {
    background: transparent;
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

#manage-cookies:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateY(-3px);
}


@media (max-width: 768px) {
    
    .cookie-content {
        flex-direction: column;
        text-align: center;
    }
    
    .cookie-text {
        text-align: center;
    }
    
    .cookie-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    #accept-cookies, 
    #reject-cookies,
    #manage-cookies {
        width: 100%;
        justify-content: center;
    }
}
</style>
</body>
</html>
