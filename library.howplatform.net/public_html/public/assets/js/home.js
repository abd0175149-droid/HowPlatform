const navToggle = document.querySelector(".nav-toggle");
const mainNav   = document.getElementById("mainnav");
const navIcon   = navToggle?.querySelector("i");

// اصنع طبقة البلور إن لم تكن موجودة
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

  if (navIcon) {
    navIcon.classList.toggle("fa-bars", !open);
    navIcon.classList.toggle("fa-xmark", open);
  }

  // قفل تمرير الصفحة عند الفتح
  document.body.style.overflow = open ? "hidden" : "";

  // لا نعدّل أي فلتر على الهيدر؛ يظل واضحًا
  // ولا نضع filter على main هنا (fallback مغطّى بالـ @supports في CSS)
}

// تفاعلات الفتح/الإغلاق + الضغط خارج القائمة
if (navToggle && mainNav) {
  navToggle.addEventListener("click", (e) => {
    e.stopPropagation();
    const ex = navToggle.getAttribute("aria-expanded") === "true";
    setNav(!ex);
  });

  // إغلاق عند الضغط على رابط داخل القائمة
  mainNav.querySelectorAll("a").forEach((a) =>
    a.addEventListener("click", () => setNav(false))
  );

  // إغلاق عند الضغط على منطقة overlay
  screenBlur.addEventListener("click", () => setNav(false));

  // إغلاق عند الضغط خارج (للدسكتوب)
  document.addEventListener("click", (e) => {
    if (mainNav.getAttribute("aria-expanded") === "true") {
      const inside = mainNav.contains(e.target) || navToggle.contains(e.target);
      if (!inside) setNav(false);
    }
  });

  // إغلاق بـ Escape
  window.addEventListener("keydown", (e) => { if (e.key === "Escape") setNav(false); });
}

/* ====================== Scroll Reveal ====================== */
const io = new IntersectionObserver((entries) => {
  entries.forEach((en) => { if (en.isIntersecting) { en.target.classList.add("active"); io.unobserve(en.target); } });
}, { threshold: 0.12 });
document.querySelectorAll(".reveal").forEach((el) => io.observe(el));

/* ====================== 3D Tilt ====================== */
document.querySelectorAll(".tilt").forEach((card) => {
  const rect = () => card.getBoundingClientRect();
  function onMove(e) {
    const r = rect();
    const x = (e.clientX - r.left) / r.width;
    const y = (e.clientY - r.top) / r.height;
    const rx = (y - 0.5) * 6;
    const ry = (x - 0.5) * -8;
    card.style.setProperty("--rx", rx + "deg");
    card.style.setProperty("--ry", ry + "deg");
    card.style.setProperty("--mx", x * 100 + "%");
    card.style.setProperty("--my", y * 100 + "%");
  }
  card.addEventListener("pointermove", onMove);
  card.addEventListener("pointerleave", () => {
    card.style.removeProperty("--rx");
    card.style.removeProperty("--ry");
    card.style.removeProperty("--mx");
    card.style.removeProperty("--my");
  });
});

/* ====================== أزرار مغناطيسية ====================== */
document.querySelectorAll(".magnet").forEach((btn) => {
  let r;
  btn.addEventListener("pointermove", (e) => {
    r = btn.getBoundingClientRect();
    const x = (e.clientX - r.left - r.width / 2) * 0.12;
    const y = (e.clientY - r.top - r.height / 2) * 0.12;
    btn.style.transform = `translate(${x}px, ${y}px)`;
  });
  btn.addEventListener("pointerleave", () => (btn.style.transform = ""));
});

/* ====================== عدادات + حلقات تقدّم ====================== */
function animateCounter(el, target, duration) {
  const start = 0;
  const t0 = performance.now();
  const easeOutCubic = (t) => 1 - Math.pow(1 - t, 3);
  function step(now) {
    const p = Math.min((now - t0) / duration, 1);
    const eased = easeOutCubic(p);
    const val = Math.floor(start + (target - start) * eased);
    el.textContent = val.toLocaleString();
    if (p < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

const rings = document.querySelectorAll(".ring");
const rio = new IntersectionObserver((entries) => {
  entries.forEach((en) => {
    if (!en.isIntersecting) return;
    const ring = en.target;
    rio.unobserve(ring);
    const numEl = ring.querySelector(".stat-number");
    const target = Number(ring.dataset.target || 0);
    const dur = Number(ring.dataset.duration || 2800);

    animateCounter(numEl, target, dur);

    // لون الحلقة: أزرق -> برتقالي تدريجيًا
    const hStart = 195, hEnd = 38;
    const lStart = 58,  lEnd = 60;
    const t0 = performance.now();
    const ease = (t) => 1 - Math.pow(1 - t, 4);

    function step(now) {
      const p = Math.min((now - t0) / dur, 1);
      const e = ease(p);
      const pct = Math.max(4, Math.floor(e * 100));
      const h = Math.round(hStart + (hEnd - hStart) * e);
      const l = Math.round(lStart + (lEnd - lStart) * e);
      const color = `hsl(${h} 86% ${l}%)`;
      ring.style.setProperty("--pct", pct);
      ring.style.setProperty("--ring-color", color);
      if (p < 1) requestAnimationFrame(step);
      else ring.style.setProperty("--pct", 100);
    }
    requestAnimationFrame(step);
  });
}, { threshold: 0.35 });
rings.forEach((r) => rio.observe(r));
