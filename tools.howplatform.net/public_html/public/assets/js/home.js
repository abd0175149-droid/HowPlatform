/* =========================================================================
 *  HOW Tools — Layout JS (Desktop sidebar reserved, Mobile off-canvas)
 *  - Desktop (≥1025px): السايدبار مفتوح افتراضيًا
 *  - Mobile  (≤1024px): السايدبار أوف-كانفس
 *  - Gate: .reveal مخفية حتى نضيف html.reveal-ready (لتفادي أي وميض)
 * ========================================================================= */
(function () {
  'use strict';

  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebar       = document.getElementById('sidebar');
  const mainContent   = document.getElementById('mainContent');
  const siteFooter    = document.getElementById('siteFooter');
  const mainNav       = document.querySelector('.main-nav'); // (اختياري) للموبايل
  const root          = document.documentElement;

  const revealEls   = Array.from(document.querySelectorAll('.reveal'));
  const animatedEls = Array.from(document.querySelectorAll('.tool-card, .feature-item'));

  const isSidebarOpen = () => !!sidebar?.classList.contains('active');

  /* ===== Intersection Observer (للحركة) ===== */
  const io = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.style.opacity   = '1';
        entry.target.style.transform = 'translateY(0)';
        // io.unobserve(entry.target); // ممكن تفعلها لو تحب مرة واحدة فقط
      }
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

  // عناصر تتحرك دائمًا (غير مرتبطة بشرط reveal)
  animatedEls.forEach((el) => {
    el.style.opacity    = '0';
    el.style.transform  = 'translateY(20px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    io.observe(el);
  });

  // جهّز عناصر reveal (لكن لا نُظهرها حتى ننتهي ونضيف reveal-ready)
  revealEls.forEach((el) => {
    el.style.opacity    = '0';
    el.style.transform  = 'translateY(20px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    // ملاحظة: لا نرصدها الآن—سنرصدها فقط عند إغلاق السايدبار
  });

  /* ===== مزامنة كلاس الحالة على <html> ===== */
  function syncRootSidebarState() {
    const open = isSidebarOpen();
    root.classList.toggle('sidebar-open', open);
    root.classList.toggle('sidebar-closed', !open);
  }

  /* ===== إدارة رصد reveal حسب حالة السايدبار ===== */
  function updateRevealVisibility() {
    if (isSidebarOpen()) {
      // السايدبار مفتوح: لا نرصد reveal وتبقى مخفية (بفعل CSS gate)
      revealEls.forEach((el) => {
        io.unobserve(el);
        el.style.opacity   = '0';
        el.style.transform = 'translateY(20px)';
      });
    } else {
      // السايدبار مغلق: ارصد reveal لتعمل حركة الظهور
      revealEls.forEach((el) => io.observe(el));
    }
  }

  /* ===== تطبيق التخطيط حسب العرض ===== */
  function applyLayoutByWidth() {
    if (!sidebar || !mainContent || !siteFooter) return;

    if (window.innerWidth > 1024) {
      // Desktop: افتح السايدبار واحجز المساحة
      sidebar.classList.add('active');
      mainContent.classList.remove('sidebar-closed');
      siteFooter.classList.remove('sidebar-closed');
      if (mainNav) mainNav.removeAttribute('aria-expanded');
    } else {
      // Mobile: أغلق السايدبار (أوف-كانفس)
      sidebar.classList.remove('active');
      mainContent.classList.remove('sidebar-closed');
      siteFooter.classList.remove('sidebar-closed');
      if (mainNav) mainNav.setAttribute('aria-expanded', 'false');
    }

    syncRootSidebarState();
    updateRevealVisibility();
  }

  /* ===== أحداث النظام ===== */
  window.addEventListener('load',   applyLayoutByWidth);
  window.addEventListener('resize', applyLayoutByWidth);

  sidebarToggle?.addEventListener('click', () => {
    if (!sidebar || !mainContent || !siteFooter) return;

    if (window.innerWidth > 1024) {
      const isOpen = sidebar.classList.toggle('active');
      if (isOpen) {
        mainContent.classList.remove('sidebar-closed');
        siteFooter.classList.remove('sidebar-closed');
      } else {
        mainContent.classList.add('sidebar-closed');
        siteFooter.classList.add('sidebar-closed');
      }
    } else {
      const isOpen = sidebar.classList.toggle('active');
      if (mainNav) mainNav.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    syncRootSidebarState();
    updateRevealVisibility();
  });

  // إغلاق عند النقر خارج (موبايل)
  document.addEventListener('click', (e) => {
    const isMobile = window.innerWidth <= 1024;
    if (!isMobile) return;

    const clickedOutsideSidebar = sidebar && !sidebar.contains(e.target);
    const clickedToggle         = sidebarToggle && sidebarToggle.contains(e.target);

    if (isSidebarOpen() && clickedOutsideSidebar && !clickedToggle) {
      sidebar.classList.remove('active');
      if (mainNav) mainNav.setAttribute('aria-expanded', 'false');
      syncRootSidebarState();
      updateRevealVisibility();
    }
  });

  // إغلاق بالـ Escape (موبايل)
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    const isMobile = window.innerWidth <= 1024;

    if (isMobile && isSidebarOpen()) {
      sidebar.classList.remove('active');
      if (mainNav) mainNav.setAttribute('aria-expanded', 'false');
      syncRootSidebarState();
      updateRevealVisibility();
    }
  });

  // هيدر أسود عند التمرير
  function updateHeaderOnScroll() {
    if (window.scrollY > 6) document.body.classList.add('is-animating');
    else                     document.body.classList.remove('is-animating');
  }
  window.addEventListener('scroll', updateHeaderOnScroll);
  updateHeaderOnScroll();

  // (اختياري) القائمة المنسدلة للمستخدم
  document.querySelectorAll('.user-dropdown .user-name')?.forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      btn.closest('.user-dropdown')?.classList.toggle('open');
    });
  });
  document.addEventListener('click', () => {
    document.querySelectorAll('.user-dropdown.open')?.forEach((dd) => dd.classList.remove('open'));
  });

  /* ========= أهم سطرين لحل الوميض =========
   * بعد أن جهّزنا كل شيء وفعلنا قواعد الحالة،
   * حرّر بوابة CSS ليُسمح لـ .reveal بالظهور فقط حسب الحالة الحالية.
   */
  syncRootSidebarState();
  updateRevealVisibility();
  root.classList.add('reveal-ready');

})();
