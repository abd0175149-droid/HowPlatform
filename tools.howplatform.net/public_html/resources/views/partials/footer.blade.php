<footer class="site-footer" id="siteFooter">
  <div class="footer-content">
    <!-- عمود: وصف المنصة -->
    <div class="footer-column">
      <h3>كيف؟ التعليمية</h3>
      <p>منصة تعليمية رائدة تهدف إلى دعم المعلمين وتمكينهم بأحدث الأدوات والموارد التعليمية لتقديم تجربة تعليمية استثنائية.</p>
    </div>

    <!-- عمود: روابط مهمة -->
    <div class="footer-column">
      <h3>روابط مهمة</h3>
      <div class="footer-links">
        <a href="{{ url('https://howplatform.net/privacy') }}" class="footer-link">
          <i class="fas fa-chevron-left" aria-hidden="true"></i> سياسة الخصوصية
        </a>
        <a href="{{ url('https://howplatform.net/terms') }}" class="footer-link">
          <i class="fas fa-chevron-left" aria-hidden="true"></i> الشروط والأحكام
        </a>
        <a href="{{ url('https://howplatform.net/faq') }}" class="footer-link">
          <i class="fas fa-chevron-left" aria-hidden="true"></i> الأسئلة الشائعة
        </a>
      </div>
    </div>

    <!-- عمود: تواصل معنا -->
    <div class="footer-column">
      <h3>تواصل معنا</h3>
      <div class="footer-links">
        <a href="mailto:support@howplatform.net" class="footer-link">
          <i class="fas fa-envelope" aria-hidden="true"></i> support@howplatform.net
        </a>
        <span class="footer-link" style="display:inline-flex;align-items:center;gap:8px;">
          <i class="fas fa-headset" aria-hidden="true"></i> الدعم الفني: متاح من الساعة 9 صباحًا إلى 5 مساءً
        </span>
      </div>
    </div>

    <!-- عمود: تابعنا -->
    <div class="footer-column">
      <h3>تابعنا</h3>
      <div class="social-links">
        <a class="social-link" aria-label="Facebook"  href="https://www.facebook.com/people/%D9%85%D9%86%D8%B5%D8%A9-%D9%83%D9%8A%D9%81-%D8%A7%D9%84%D8%AA%D8%B9%D9%84%D9%8A%D9%85%D9%8A%D8%A9-%D8%A7%D9%84%D8%AF%D8%A8%D9%84%D9%88%D9%85-%D8%A7%D9%84%D8%B9%D8%A7%D9%84%D9%8A/61579341362175/" target="_blank" rel="noopener">
          <i class="fab fa-facebook-f" aria-hidden="true"></i>
        </a>
        <a class="social-link" aria-label="Instagram" href="https://www.instagram.com/diploma_how?utm_source=ig_web_button_share_sheet&igsh=Z2RyM3V1djF5cnlm" target="_blank" rel="noopener">
          <i class="fab fa-instagram" aria-hidden="true"></i>
        </a>
        <a class="social-link" aria-label="Telegram" href="https://t.me/howplatform_diploma" target="_blank" rel="noopener">
          <i class="fab fa-telegram-plane" aria-hidden="true"></i>
        </a>
        <a class="social-link" aria-label="WhatsApp" href="https://wa.me/962782550901" target="_blank" rel="noopener">
          <i class="fab fa-whatsapp" aria-hidden="true"></i>
        </a>
      </div>
      <p class="social-note">نسعد بتواصلك عبر أي قناة تناسبك.</p>
    </div>
  </div>

  <div class="footer-bottom">
    <p>© {{ now()->year }} howplatform.net - جميع الحقوق محفوظة</p>
  </div>
</footer>
