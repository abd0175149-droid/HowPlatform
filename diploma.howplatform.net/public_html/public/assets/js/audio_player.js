/* =========================================================================
 * AudioPlayer v1.0.2
 * - إصلاح تحريك المؤشر من أول تشغيل
 * - منع تداخل أوامر التقديم/الترجيع
 * - كشف مدة الملف حتى مع WebM العنيدة (fallback)
 * - تحميل صريح + تهيئة منطقية قبل التشغيل
 * ========================================================================= */
window.AudioPlayer = (function () {
  const SPEEDS = [0.75, 1, 1.25, 1.5, 1.75, 2];
  const JUMP = 10; // ثواني التقديم/الترجيع

  function fmt(t) {
    if (!isFinite(t)) return "0:00";
    const m = Math.floor(t / 60);
    const s = Math.floor(t % 60).toString().padStart(2, "0");
    return `${m}:${s}`;
  }
  const qs = (el, sel) => el.querySelector(sel);
  const clamp = (v, min, max) => Math.min(Math.max(v, min), max);

  // محاولة تقدير المدة عبر WebAudio (fallback)
  async function probeDuration(url) {
    try {
      const AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) return 0;
      const ac = new AC();
      const res = await fetch(url, { cache: "no-store" });
      const buf = await res.arrayBuffer();
      const decoded = await ac.decodeAudioData(buf);
      return decoded.duration || 0;
    } catch {
      return 0;
    }
  }

  // Promise: نضمن جهوزية المدة (مع حيل القفزة + WebAudio)
  function ensureDuration(audio, url) {
    return new Promise((resolve) => {
      if (isFinite(audio.duration) && audio.duration > 0) {
        return resolve();
      }

      let settled = false;
      const finish = () => {
        if (settled) return;
        settled = true;
        resolve();
      };

      const onMeta = () => finish();
      audio.addEventListener("loadedmetadata", onMeta, { once: true });
      audio.addEventListener("durationchange", onMeta, { once: true });

      // حيلة القفزة الكبيرة
      try {
        audio.currentTime = 1e101;
        audio.ontimeupdate = function () {
          audio.ontimeupdate = null;
          try {
            audio.currentTime = 0;
          } catch {}
          // لو بقيت غير معروفة بعدها، جرّب WebAudio بشكل غير حابس للتشغيل
          setTimeout(async () => {
            if (!(isFinite(audio.duration) && audio.duration > 0)) {
              const d = await probeDuration(url);
              // ما نقدر نكتب duration للعنصر، لكن ممكن نعتمدها للعرض فقط
              audio._ap_fallbackDuration = d || 0;
            }
            finish();
          }, 200);
        };
      } catch {
        // fallback سريع
        setTimeout(async () => {
          if (!(isFinite(audio.duration) && audio.duration > 0)) {
            const d = await probeDuration(url);
            audio._ap_fallbackDuration = d || 0;
          }
          finish();
        }, 400);
      }
    });
  }

  function mount(root, src) {
    if (!root || root._apMounted) return;
    root._apMounted = true;

    const audio = qs(root, "audio");
    const btnPlay = qs(root, ".ap-play");
    const btnMute = qs(root, ".ap-mute");
    const rngSeek = qs(root, ".ap-seek");
    const rngVol = qs(root, ".ap-volume");
    const lblCur = qs(root, ".ap-cur");
    const lblDur = qs(root, ".ap-dur");
    const btnSpd = qs(root, ".ap-speed");
    const btnFwd = qs(root, ".ap-forward");
    const btnRew = qs(root, ".ap-rewind");

    if (!audio || !btnPlay || !rngSeek || !lblCur || !lblDur) {
      console.warn("AudioPlayer: عناصر أساسية ناقصة داخل", root);
      return;
    }

    // حالة لكل لاعب (تمنع التضارب)
    const state = {
      rafId: null,
      speedIndex: 1,
      isDragging: false, // المستخدم يسحب السلايدر الآن
      isJumping: false, // قفزة 10 ث جارية (حماية من السباقات)
      lastTickTime: 0,
    };
    root._ap = state;

    // إعداد المصدر والتحميل الصريح
    let url = src || root.getAttribute("data-src");
    if (!url) {
      console.warn("AudioPlayer: data-src مفقود");
      return;
    }

    // إجبار التحميل
    audio.preload = "auto";
    audio.src = url;
    audio.load();

    // تهيئة النصوص
    lblCur.textContent = "0:00";
    lblDur.textContent = "0:00";

    // ===== RAF
    const tick = (t) => {
      // throttle خفيف ~60fps
      if (!audio.paused && !audio.ended) {
        const duration =
          (isFinite(audio.duration) && audio.duration > 0
            ? audio.duration
            : audio._ap_fallbackDuration) || 0;

        if (!state.isDragging && duration > 0) {
          rngSeek.value = (audio.currentTime / duration) * 100;
        }
        lblCur.textContent = fmt(audio.currentTime);
        state.rafId = requestAnimationFrame(tick);
      }
    };
    const startRaf = () => {
      if (state.rafId) cancelAnimationFrame(state.rafId);
      state.rafId = requestAnimationFrame(tick);
    };
    const stopRaf = () => {
      if (state.rafId) cancelAnimationFrame(state.rafId);
      state.rafId = null;
    };

    // سرعة
    audio.playbackRate = SPEEDS[state.speedIndex];
    if (btnSpd) btnSpd.textContent = SPEEDS[state.speedIndex] + "x";

    // جهّز المدة قبل أي تشغيل (ولا نحجب المستخدم لو تأخرت)
    ensureDuration(audio, url).then(() => {
      const d =
        (isFinite(audio.duration) && audio.duration > 0
          ? audio.duration
          : audio._ap_fallbackDuration) || 0;
      lblDur.textContent = fmt(d);
      lblCur.textContent = fmt(audio.currentTime || 0);
    });

    // تشغيل/إيقاف
    btnPlay.addEventListener("click", async () => {
      try {
        if (audio.paused) {
          // بعض المتصفحات تحتاج user gesture سابق، لكن نحاول
          await audio.play();
        } else {
          audio.pause();
        }
      } catch (e) {
        console.warn("Audio play error:", e);
      }
    });

    audio.addEventListener("play", () => {
      btnPlay.innerHTML = '<i class="fas fa-pause"></i>';
      startRaf(); // يضمن تحريك المؤشر من أول لحظة
    });
    audio.addEventListener("playing", () => {
      // playing يأتي بعد تحضير كافٍ — حدّث الملصقات
      const d =
        (isFinite(audio.duration) && audio.duration > 0
          ? audio.duration
          : audio._ap_fallbackDuration) || 0;
      lblDur.textContent = fmt(d);
      lblCur.textContent = fmt(audio.currentTime || 0);
    });
    audio.addEventListener("pause", () => {
      btnPlay.innerHTML = '<i class="fas fa-play"></i>';
      stopRaf();
    });
    audio.addEventListener("ended", () => {
      stopRaf();
      rngSeek.value = 0;
      lblCur.textContent = fmt(0);
      btnPlay.innerHTML = '<i class="fas fa-play"></i>';
    });

    // الميوت/الصوت
    if (btnMute) {
      btnMute.addEventListener("click", () => {
        audio.muted = !audio.muted;
        btnMute.classList.toggle("is-muted", audio.muted);
        btnMute.innerHTML = audio.muted
          ? '<i class="fas fa-volume-mute"></i>'
          : '<i class="fas fa-volume-up"></i>';
      });
    }
    if (rngVol) {
      rngVol.addEventListener("input", () => {
        audio.volume = Number(rngVol.value);
        if (btnMute) {
          if (audio.volume === 0 && !audio.muted) {
            audio.muted = true;
            btnMute.classList.add("is-muted");
            btnMute.innerHTML = '<i class="fas fa-volume-mute"></i>';
          }
          if (audio.volume > 0 && audio.muted) {
            audio.muted = false;
            btnMute.classList.remove("is-muted");
            btnMute.innerHTML = '<i class="fas fa-volume-up"></i>';
          }
        }
      });
    }

    // تحديثات وقت/مؤشر (احتياطي بجانب RAF)
    audio.addEventListener("timeupdate", () => {
      if (state.isDragging) return; // لا تتعارض مع سحب المستخدم
      const d =
        (isFinite(audio.duration) && audio.duration > 0
          ? audio.duration
          : audio._ap_fallbackDuration) || 0;
      if (d > 0) rngSeek.value = (audio.currentTime / d) * 100;
      lblCur.textContent = fmt(audio.currentTime);
    });

    // سحب السلايدر (بدون خلط/قفزات)
    const commitSeek = (pct) => {
      const d =
        (isFinite(audio.duration) && audio.duration > 0
          ? audio.duration
          : audio._ap_fallbackDuration) || 0;
      if (d <= 0) return; // لا نسحب لو المدة غير معروفة فعلاً
      audio.currentTime = clamp((pct / 100) * d, 0, d);
      lblCur.textContent = fmt(audio.currentTime);
    };

    rngSeek.addEventListener("pointerdown", () => (state.isDragging = true));
    rngSeek.addEventListener("pointerup", () => {
      state.isDragging = false;
      commitSeek(Number(rngSeek.value));
    });
    rngSeek.addEventListener("change", () => {
      if (!state.isDragging) commitSeek(Number(rngSeek.value));
    });
    rngSeek.addEventListener("input", () => {
      // تحديث بصري فقط أثناء السحب
      const d =
        (isFinite(audio.duration) && audio.duration > 0
          ? audio.duration
          : audio._ap_fallbackDuration) || 0;
      if (d > 0) {
        const cur = clamp((Number(rngSeek.value) / 100) * d, 0, d);
        lblCur.textContent = fmt(cur);
      }
    });

    // سرعة
    if (btnSpd) {
      btnSpd.addEventListener("click", () => {
        state.speedIndex = (state.speedIndex + 1) % SPEEDS.length;
        audio.playbackRate = SPEEDS[state.speedIndex];
        btnSpd.textContent = SPEEDS[state.speedIndex] + "x";
      });
    }

    // تقديم/ترجيع (محمي من السباقات)
    const safeJump = async (delta) => {
      if (state.isJumping) return;
      state.isJumping = true;
      try {
        const d =
          (isFinite(audio.duration) && audio.duration > 0
            ? audio.duration
            : audio._ap_fallbackDuration) || 0;
        if (d <= 0) return; // لا نقفز إذا ما في مدة معروفة إطلاقًا

        const next = clamp((audio.currentTime || 0) + delta, 0, d);
        audio.currentTime = next;
        lblCur.textContent = fmt(next);
        // نسمح للإطار التالي يثبت القراءة
        await new Promise((r) => requestAnimationFrame(r));
      } finally {
        state.isJumping = false;
      }
    };

    if (btnFwd) {
      btnFwd.title = "تقديم ١٠ ثوانٍ";
      btnFwd.setAttribute("aria-label", "تقديم 10 ثوانٍ");
      btnFwd.addEventListener("click", () => safeJump(+JUMP));
    }
    if (btnRew) {
      btnRew.title = "ترجيع ١٠ ثوانٍ";
      btnRew.setAttribute("aria-label", "ترجيع 10 ثوانٍ");
      btnRew.addEventListener("click", () => safeJump(-JUMP));
    }

    // تنظيف عند إزالة العنصر
    const observer = new MutationObserver(() => {
      if (!document.body.contains(root)) {
        try {
          stopRaf();
          audio.pause();
          audio.src = "";
        } catch {}
        observer.disconnect();
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });

    return root;
  }

  function mountAll(ctx) {
    (ctx || document).querySelectorAll(".apjs").forEach((el) => mount(el));
  }

  return { mount, mountAll };
})();
