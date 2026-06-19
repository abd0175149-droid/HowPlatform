/**
 * HOWPlatform — Theme Manager v2.1
 * www.howplatform.net | كيف التعليمية
 *
 * Priority: localStorage['how-theme'] → prefers-color-scheme → 'light'
 * Logo: colored (Logo_how_gr.svg) in light mode, white (Logo_how_w.svg) in dark mode
 */

const HOWTheme = (() => {
  const STORAGE_KEY = 'how-theme';
  const THEMES      = ['light', 'dark'];
  const TOGGLE_ID   = 'how-theme-toggle';
  const LOGO_ID     = 'how-logo-img';

  /* ── SVG Icons ── */
  const ICON_SUN = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
    fill="none" stroke="currentColor" stroke-width="2.2"
    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <circle cx="12" cy="12" r="4"/>
    <line x1="12" y1="2"  x2="12" y2="6"/>
    <line x1="12" y1="18" x2="12" y2="22"/>
    <line x1="4.93" y1="4.93" x2="7.76"  y2="7.76"/>
    <line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/>
    <line x1="2"  y1="12" x2="6"  y2="12"/>
    <line x1="18" y1="12" x2="22" y2="12"/>
    <line x1="4.93" y1="19.07" x2="7.76"  y2="16.24"/>
    <line x1="16.24" y1="7.76"  x2="19.07" y2="4.93"/>
  </svg>`;

  const ICON_MOON = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
    fill="none" stroke="currentColor" stroke-width="2.2"
    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
  </svg>`;

  /* ── Get current from <html> ── */
  function getCurrent() {
    return document.documentElement.getAttribute('data-theme') || 'light';
  }

  /* ── Apply theme ── */
  function apply(theme, save = true) {
    if (!THEMES.includes(theme)) theme = 'light';
    const isDark = theme === 'dark';

    /* 1. Set attribute on <html> */
    document.documentElement.setAttribute('data-theme', theme);

    /* 2. Save preference */
    if (save) {
      try { localStorage.setItem(STORAGE_KEY, theme); } catch (_) {}
    }

    /* 3. Update toggle button */
    const btn = document.getElementById(TOGGLE_ID);
    if (btn) {
      btn.innerHTML = isDark ? ICON_SUN : ICON_MOON;
      btn.setAttribute('aria-label', isDark ? 'التبديل إلى الوضع الفاتح' : 'التبديل إلى الوضع المظلم');
      btn.setAttribute('title',      isDark ? 'الوضع الفاتح' : 'الوضع المظلم');
      btn.setAttribute('data-theme-current', theme);

      /* Animate toggle */
      btn.classList.remove('theme-btn--anim');
      void btn.offsetWidth; /* reflow */
      btn.classList.add('theme-btn--anim');
    }

    /* 4. Swap logo:
         Light mode → colored logo (Logo_how_gr.svg)
         Dark  mode → white  logo (Logo_how_w.svg)   */
    const logo = document.getElementById(LOGO_ID);
    if (logo) {
      const colorSrc = logo.getAttribute('data-logo-color');
      const whiteSrc = logo.getAttribute('data-logo-white');
      logo.style.transition = 'opacity 0.25s ease';
      logo.style.opacity    = '0';
      setTimeout(() => {
        logo.src          = isDark ? whiteSrc : colorSrc;
        logo.style.opacity = '1';
      }, 150);
    }

    /* 5. Dispatch event */
    document.dispatchEvent(new CustomEvent('how:themechange', { detail: { theme } }));
  }

  /* ── Toggle ── */
  function toggle() {
    apply(getCurrent() === 'dark' ? 'light' : 'dark');
  }

  /* ── Resolve initial theme (priority chain) ── */
  function resolve() {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved && THEMES.includes(saved)) return saved;
    } catch (_) {}
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  /* ── Full init (called after DOM ready) ── */
  function init() {
    /* Apply full UI (icons, logo, etc.) based on resolved theme */
    apply(resolve(), false);

    /* Bind toggle button */
    const btn = document.getElementById(TOGGLE_ID);
    if (btn) btn.addEventListener('click', toggle);

    /* Watch OS-level preference changes (only when no saved preference) */
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
      try {
        if (!localStorage.getItem(STORAGE_KEY)) apply(e.matches ? 'dark' : 'light', false);
      } catch (_) {}
    });
  }

  return { init, toggle, apply, getCurrent, resolve };
})();

/* ── Run after DOM ready ── */
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', HOWTheme.init);
} else {
  HOWTheme.init();
}

/* ── Expose globally ── */
window.HOWTheme = HOWTheme;
