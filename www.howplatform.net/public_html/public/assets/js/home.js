/* home.js v3.0 — HOWPlatform
   لا حركة جافاسكريبت على العناصر — تفاعل لوني فقط */

/* ════════════════════════════════════════
   1. خلفية Particles (canvas #fxbg)
   ════════════════════════════════════════ */
(function () {
  const c = document.getElementById("fxbg");
  if (!c || !(c instanceof HTMLCanvasElement)) return;
  const ctx = c.getContext("2d");
  let w, h, prt = [];

  function resize() {
    w = c.width  = innerWidth;
    h = c.height = innerHeight;
  }
  addEventListener("resize", resize, { passive: true });
  resize();

  const N = Math.min(80, Math.floor((w * h) / 30000));
  function reset(p) {
    p.x   = Math.random() * w;
    p.y   = Math.random() * h;
    p.vx  = (Math.random() - 0.5) * 0.2;
    p.vy  = (Math.random() - 0.5) * 0.2;
    p.r   = Math.random() * 1.5 + 0.5;
    p.a   = Math.random() * 0.5 + 0.2;
    p.hue = 200 + Math.random() * 25;
  }
  for (let i = 0; i < N; i++) { prt[i] = {}; reset(prt[i]); }

  function loop() {
    ctx.clearRect(0, 0, w, h);
    for (const p of prt) {
      p.x += p.vx;
      p.y += p.vy;
      if (p.x < -50 || p.x > w + 50 || p.y < -50 || p.y > h + 50) reset(p);
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle = `hsla(${p.hue},75%,62%,${p.a})`;
      ctx.fill();
    }
    requestAnimationFrame(loop);
  }
  loop();
})();

/* ════════════════════════════════════════
   2. Navigation Toggle (Mobile)
   ════════════════════════════════════════ */
(function () {
  const navToggle  = document.querySelector(".nav-toggle");
  const mainNav    = document.getElementById("mainnav");
  const navIcon    = navToggle?.querySelector("i");

  // طبقة التعتيم
  let screenBlur = document.getElementById("screen-blur");
  if (!screenBlur) {
    screenBlur = document.createElement("div");
    screenBlur.id = "screen-blur";
    document.body.appendChild(screenBlur);
  }

  function setNav(open) {
    if (!navToggle || !mainNav) return;
    navToggle.setAttribute("aria-expanded", open);
    mainNav.setAttribute("aria-expanded", open);
    document.body.classList.toggle("nav-open", open);
    document.body.style.overflow = open ? "hidden" : "";
    if (navIcon) {
      navIcon.classList.toggle("fa-bars",  !open);
      navIcon.classList.toggle("fa-xmark",  open);
    }
  }

  if (navToggle && mainNav) {
    navToggle.addEventListener("click", (e) => {
      e.stopPropagation();
      setNav(navToggle.getAttribute("aria-expanded") !== "true");
    });

    mainNav.querySelectorAll("a").forEach((a) =>
      a.addEventListener("click", () => setNav(false))
    );

    screenBlur.addEventListener("click", () => setNav(false));

    document.addEventListener("click", (e) => {
      if (mainNav.getAttribute("aria-expanded") === "true") {
        if (!mainNav.contains(e.target) && !navToggle.contains(e.target)) setNav(false);
      }
    });

    window.addEventListener("keydown", (e) => { if (e.key === "Escape") setNav(false); });
  }
})();

/* ════════════════════════════════════════
   3. Scroll Reveal
   ════════════════════════════════════════ */
(function () {
  const io = new IntersectionObserver((entries) => {
    entries.forEach((en) => {
      if (en.isIntersecting) {
        en.target.classList.add("active");
        io.unobserve(en.target);
      }
    });
  }, { threshold: 0.12 });
  document.querySelectorAll(".reveal").forEach((el) => io.observe(el));
})();

/* ════════════════════════════════════════
   4. Stat Counters + Progress Rings
   ════════════════════════════════════════ */
(function () {
  function animateCounter(el, target, duration) {
    const t0 = performance.now();
    const ease = (t) => 1 - Math.pow(1 - t, 3);
    function step(now) {
      const p = Math.min((now - t0) / duration, 1);
      el.textContent = Math.floor(ease(p) * target).toLocaleString();
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  const rings = document.querySelectorAll(".ring");
  if (!rings.length) return;

  const rio = new IntersectionObserver((entries) => {
    entries.forEach((en) => {
      if (!en.isIntersecting) return;
      const ring = en.target;
      rio.unobserve(ring);

      const numEl  = ring.querySelector(".stat-number");
      const target = Number(ring.dataset.target   || 0);
      const dur    = Number(ring.dataset.duration || 2800);

      if (numEl) animateCounter(numEl, target, dur);

      // حلقة التقدم
      const hStart = 195, hEnd = 38;
      const t0 = performance.now();
      const ease = (t) => 1 - Math.pow(1 - t, 4);
      function step(now) {
        const p = Math.min((now - t0) / dur, 1);
        const e = ease(p);
        ring.style.setProperty("--pct", Math.max(4, Math.floor(e * 100)));
        ring.style.setProperty("--ring-color",
          `hsl(${Math.round(hStart + (hEnd - hStart) * e)} 86% 59%)`);
        if (p < 1) requestAnimationFrame(step);
        else ring.style.setProperty("--pct", 100);
      }
      requestAnimationFrame(step);
    });
  }, { threshold: 0.35 });

  rings.forEach((r) => rio.observe(r));
})();
