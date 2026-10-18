/* ============================================================
   FateenCharacter.js — تحريك شخصية فَطين تفاعلياً عبر GSAP
   - المستوى 1: تنفّس خمول، إمالة تجاه المؤشر، ارتداد عند الرد، اهتزاز/تكلّم أثناء الكتابة
   - المستوى 2: العيون تتبع المؤشر، رمش دوري، فم يتحرك أثناء كتابة الرد
   يعمل على كل نسخ الشخصية المضمّنة (الزر العائم + رأس النافذة)، ويحترم prefers-reduced-motion.
   ============================================================ */
(function () {
  if (typeof window.gsap === "undefined") return;

  const reduceMotion =
    window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  const hosts = document.querySelectorAll(".fateen-svg-host");
  if (!hosts.length) return;

  const clamp = gsap.utils.clamp;
  const controllers = [];

  hosts.forEach((host) => {
    const svg = host.querySelector("svg");
    if (!svg) return; // fallback <img> — لا تحريك

    const eyes = svg.querySelector("#fateen-eyes");
    const mouth = svg.querySelector("#fateen-mouth");

    // محاور التحويل
    gsap.set(svg, { transformOrigin: "50% 80%" });
    if (eyes) gsap.set(eyes, { transformOrigin: "50% 50%" });
    if (mouth) gsap.set(mouth, { transformOrigin: "50% 50%" });

    const c = { host, svg, eyes, mouth, talking: false, mouthTl: null };

    if (!reduceMotion) {
      // تنفّس/تعويم خمول
      gsap.to(svg, {
        y: -2,
        duration: 1.5,
        ease: "sine.inOut",
        yoyo: true,
        repeat: -1,
      });

      // دوال سلسة للإمالة وحركة العيون
      c.rotTo = gsap.quickTo(svg, "rotation", { duration: 0.5, ease: "power3" });
      if (eyes) {
        c.eyeX = gsap.quickTo(eyes, "x", { duration: 0.4, ease: "power3" });
        c.eyeY = gsap.quickTo(eyes, "y", { duration: 0.4, ease: "power3" });
      }

      // رمش دوري
      const scheduleBlink = () => {
        c.blinkTimer = setTimeout(() => {
          if (eyes && !c.talking) {
            gsap
              .timeline()
              .to(eyes, { scaleY: 0.1, duration: 0.08, ease: "power1.in" })
              .to(eyes, { scaleY: 1, duration: 0.13, ease: "power1.out" });
          }
          scheduleBlink();
        }, (2 + Math.random() * 4) * 1000);
      };
      scheduleBlink();
    }

    controllers.push(c);
  });

  if (!controllers.length) return;

  if (!reduceMotion) {
    // إمالة الشخصية + العيون تتبع المؤشر (لكل نسخة مرئية)
    document.addEventListener(
      "mousemove",
      (e) => {
        controllers.forEach((c) => {
          const r = c.svg.getBoundingClientRect();
          if (!r.width) return; // النسخة مخفية حالياً
          const cx = r.left + r.width / 2;
          const cy = r.top + r.height / 2;
          const dx = (e.clientX - cx) / window.innerWidth; // ~ -0.5..0.5
          const dy = (e.clientY - cy) / window.innerHeight;
          if (c.rotTo) c.rotTo(clamp(-10, 10, dx * 36));
          if (c.eyeX) c.eyeX(clamp(-15, 15, dx * 40));
          if (c.eyeY && !c.talking) c.eyeY(clamp(-12, 12, dy * 32));
        });
      },
      { passive: true }
    );
  }

  // ===== تفاعلات تُستدعى بالأحداث =====
  function reactReply() {
    if (reduceMotion) return;
    controllers.forEach((c) => {
      gsap.fromTo(
        c.svg,
        { scale: 1 },
        { scale: 1.16, duration: 0.18, ease: "back.out(3)", yoyo: true, repeat: 1 }
      );
    });
  }

  // ملاحظة: أُزيل أنيميشن الفم بناءً على طلب المستخدم.
  // نُبقي حالة "talking" فقط لإيقاف تتبّع العين العمودي أثناء الكتابة.
  function startTalking() {
    controllers.forEach((c) => { c.talking = true; });
  }

  function stopTalking() {
    controllers.forEach((c) => { c.talking = false; });
  }

  // ===== الربط بحالة الكتابة والرد (بدون تعديل منطق الدردشة) =====
  const typing = document.getElementById("typing-indicator");
  if (typing) {
    const moT = new MutationObserver(() => {
      const visible = getComputedStyle(typing).display !== "none";
      if (visible) startTalking();
      else stopTalking();
    });
    moT.observe(typing, { attributes: true, attributeFilter: ["style", "class"] });
  }

  const msgs = document.getElementById("chat-messages");
  if (msgs) {
    const moM = new MutationObserver((muts) => {
      for (const m of muts) {
        for (const n of m.addedNodes) {
          if (
            n.nodeType === 1 &&
            n.classList &&
            n.classList.contains("received")
          ) {
            reactReply();
            return;
          }
        }
      }
    });
    moM.observe(msgs, { childList: true });
  }
})();
