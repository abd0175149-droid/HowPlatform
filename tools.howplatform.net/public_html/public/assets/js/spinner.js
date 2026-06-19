(function () {
  const BOOT = window.SPINNER_BOOT || {};
  const $ = (s, r = document) => r.querySelector(s);

  // عناصر الصفحة
  const svg = $('#spinner-svg');
  const wheelLayer = $('#wheel');
  const spinBtn = $('#btn-spin');
  const muteBtn = $('#btn-mute');
  const fsBtn = $('#btn-fullscreen');
  const winnerBox = $('#winnerBox');
  const winnerName = $('#winnerName');
  const closeWinnerBtn = $('#btn-close-winner');

  const itemsList = $('#itemsList'); // موجود في العمود الأيمن
  const rgSec = $('#rgSpinSec');
  const spinVal = $('#spinVal');
  const selRemove = $('#selRemoveRule');
  const btnSaveSettings = $('#btnSaveSettings');

  const spinSound = $('#spinSound');
  const tickSound = $('#tickSound');
  const winSound = $('#winSound');

  const qrBtn = $('#btn-qrcode');
  const qrModal = $('#qrModal');
  const qrBox = $('#qrBox');
  const qrDownload = $('#qrDownload');
  const qrClose = $('#qrClose');
  const copyLink = $('#copyLink');

  // حالة
  let soundOn = !!BOOT.soundOn;
  let spinMs = BOOT.spinMs || 8000;
  let running = false;
  let currentAngle = 0;

  // تفضيل تقليل الحركة
  const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // جمع العناصر النشطة من القائمة (نستخدم id+label ليطابق الفائز)
  function readActiveItems() {
    const arr = [];
    itemsList.querySelectorAll('.item-toggle').forEach(ch => {
      const id = +ch.dataset.id;
      const label = ch.parentElement.querySelector('span').textContent.trim();
      if (ch.checked) arr.push({ id, label });
    });
    return arr;
  }

  // رسم عجلة SVG (قطاعات + تسميات منحنية)
  function buildWheelSVG(items) {
    wheelLayer.innerHTML = '';
    if (!items.length) return;

    const n = items.length;
    const step = (Math.PI * 2) / n;
    const R = 300, rText = 200;

    items.forEach((it, i) => {
      const start = i * step - Math.PI / 2;        // نبدأ من الأعلى
      const end = start + step;
      const large = end - start > Math.PI ? 1 : 0;

      // حساب نقاط القوس
      const x0 = Math.cos(start) * 0;
      const y0 = Math.sin(start) * 0;
      const x1 = Math.cos(start) * R;
      const y1 = Math.sin(start) * R;
      const x2 = Math.cos(end) * R;
      const y2 = Math.sin(end) * R;

      // لون متدرّج حي (ثابت لكنه متّسق)
      const hue = (i * (360 / n)) % 360;
      const fill = `hsl(${hue}, 75%, 55%)`;

      const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      // قطاع مثلثي بقوس خارجي (من المركز إلى الحافة ثم قوس ثم عودة للمركز)
      path.setAttribute('d', `M0,0 L${x1},${y1} A${R},${R} 0 ${large} 1 ${x2},${y2} Z`);
      path.setAttribute('fill', fill);
      path.setAttribute('data-id', it.id);
      path.setAttribute('data-index', i);
      path.setAttribute('aria-label', it.label);
      wheelLayer.appendChild(path);

      // مسار للنص على نصف القطر (قريب من الحافة الداخلية)
      const mid = start + step / 2;
      const tx1 = Math.cos(mid - 0.02) * rText;
      const ty1 = Math.sin(mid - 0.02) * rText;
      const tx2 = Math.cos(mid + 0.02) * rText;
      const ty2 = Math.sin(mid + 0.02) * rText;

      const tid = `t${i}-${Date.now()}`;
      const tpath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      tpath.setAttribute('id', tid);
      tpath.setAttribute('d', `M ${tx1},${ty1} A ${rText},${rText} 0 0 1 ${tx2},${ty2}`);
      tpath.setAttribute('fill', 'none');
      tpath.setAttribute('stroke', 'none');
      wheelLayer.appendChild(tpath);

      const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      text.setAttribute('font-size', '16');
      text.setAttribute('fill', '#111');
      text.setAttribute('text-anchor', 'middle');
      const textPath = document.createElementNS('http://www.w3.org/2000/svg', 'textPath');
      textPath.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', `#${tid}`);
      textPath.setAttribute('startOffset', '50%');
      textPath.textContent = it.label;
      text.appendChild(textPath);
      wheelLayer.appendChild(text);
    });
  }

  // بناء العجلة أول مرة
  let activeItems = readActiveItems();
  buildWheelSVG(activeItems);

  // تحديث عند تغيير العناصر
  itemsList?.addEventListener('change', async (e) => {
    const ch = e.target;
    if (!ch.classList.contains('item-toggle')) return;
    const id = +ch.dataset.id;
    const active = ch.checked ? 1 : 0;

    await fetch(BOOT.endpoints.toggle, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': BOOT.csrf, 'Content-Type': 'application/json' },
      body: JSON.stringify({ item_id: id, active })
    });

    activeItems = readActiveItems();
    buildWheelSVG(activeItems);
  });

  // إضافة عناصر جديدة
  const formAdd = $('#formAddItems');
  formAdd?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(formAdd);
    const res = await fetch(BOOT.endpoints.add, { method: 'POST', headers: { 'X-CSRF-TOKEN': BOOT.csrf }, body: fd });
    const j = await res.json();
    if (j.ok) location.reload();
  });

  // حفظ الإعدادات
  rgSec?.addEventListener('input', () => { spinVal.textContent = rgSec.value; });
  btnSaveSettings?.addEventListener('click', async () => {
    const sec = Math.max(5, Math.min(15, parseInt(rgSec.value || 8, 10)));
    const snd = document.querySelector('input[name="sound_on"]:checked')?.value || '1';
    const rem = selRemove.value || 'none';
    const res = await fetch(BOOT.endpoints.save, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': BOOT.csrf, 'Content-Type': 'application/json' },
      body: JSON.stringify({ spin_ms: sec * 1000, sound_on: snd, remove_rule: rem })
    });
    const j = await res.json();
    if (j.ok) { spinMs = sec * 1000; alert('تم حفظ الإعدادات.'); }
  });

  // صوت
  function play(el) { if (soundOn && el) { try { el.currentTime = 0; el.play(); } catch (_) {} } }
  function stop(el) { if (el) { try { el.pause(); } catch (_) {} } }
  muteBtn?.addEventListener('click', () => {
    soundOn = !soundOn;
    muteBtn.textContent = soundOn ? 'كتم الصوت' : 'تشغيل الصوت';
    muteBtn.setAttribute('aria-pressed', soundOn ? 'false' : 'true');
  });

  // دوران: نحسب زاوية مستهدفة بحيث يتوقف القطاع الفائز تحت المؤشر (عند 0° فوق)
  async function spin() {
    if (running) return;
    if (!activeItems.length) { alert('لا توجد عناصر مفعّلة'); return; }

    running = true;

    // اطلب الفائز من الخادم (يسجل النتيجة ويطبّق الحذف)
    const res = await fetch(BOOT.endpoints.spin, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': BOOT.csrf }
    });
    const j = await res.json();
    if (!j.ok) { running = false; alert(j.error || 'تعذر الدوران'); return; }

    const winner = j.winner;
    // اعثر على إندكس الفائز بين العناصر النشطة عبر id
    const idx = activeItems.findIndex(it => String(it.id) === String(winner.id));
    if (idx < 0) { running = false; alert('تعذر مطابقة الفائز.'); return; }

    const n = activeItems.length;
    const stepDeg = 360 / n;

    // نريد أن يكون "وسط القطاع الفائز" عند الزاوية 0 (تحت المؤشر)
    const targetCenter = idx * stepDeg + stepDeg / 2;
    // نحسب كم نلف من الزاوية الحالية للوصول للهدف + دورات إضافية
    const turns = reduceMotion ? 1 : 6 + Math.random() * 2; // دورات إضافية لجمال الحركة
    const targetAngle = 360 * turns - targetCenter;

    const total = reduceMotion ? Math.min(spinMs, 1200) : spinMs;
    const start = performance.now();
    const easeOutCubic = (t) => 1 - Math.pow(1 - t, 3);

    play(spinSound);
    let lastTick = 0;

    function frame(now) {
      const t = Math.min(1, (now - start) / total);
      const p = easeOutCubic(t);
      const ang = currentAngle + (targetAngle - currentAngle) * p;
      wheelLayer.setAttribute('transform', `rotate(${ang})`);

      if (!reduceMotion && now - lastTick > 110) { play(tickSound); lastTick = now; }

      if (t < 1) requestAnimationFrame(frame);
      else {
        stop(spinSound);
        play(winSound);
        currentAngle = targetAngle % 360;
        winnerName.textContent = winner.label || '—';
        winnerBox.style.display = 'block';
        running = false;
        // قد تتغير العناصر (الحذف) — نحدّث بعد قليل
        setTimeout(() => location.reload(), 400);
      }
    }
    requestAnimationFrame(frame);
  }

  spinBtn?.addEventListener('click', spin);
  // لوحة مفاتيح: Enter/Space
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.code === 'Space') {
      if (document.activeElement === spinBtn || document.activeElement === document.body) {
        e.preventDefault(); spin();
      }
    }
    if (e.key.toLowerCase() === 'm') muteBtn?.click();
    if (e.key.toLowerCase() === 'f') fsBtn?.click();
  });

  // إغلاق الفائز
  closeWinnerBtn?.addEventListener('click', () => { winnerBox.style.display = 'none'; });

  // ملء الشاشة
  fsBtn?.addEventListener('click', () => {
    const el = $('#wheelCard');
    if (document.fullscreenElement) document.exitFullscreen();
    else el?.requestFullscreen?.();
  });

  // QR
  qrBtn?.addEventListener('click', () => {
    qrModal.style.display = 'block';
    qrBox.innerHTML = '';
    new QRCode(qrBox, { text: BOOT.shareUrl, width: 220, height: 220 });
  });
  qrClose?.addEventListener('click', () => { qrModal.style.display = 'none'; });
  qrDownload?.addEventListener('click', () => {
    const img = qrBox.querySelector('img') || qrBox.querySelector('canvas');
    if (!img) return;
    const a = document.createElement('a');
    a.download = 'spinner-qr.png';
    a.href = img.tagName === 'IMG' ? img.src : img.toDataURL('image/png');
    a.click();
  });
  copyLink?.addEventListener('click', async () => {
    await navigator.clipboard.writeText(BOOT.shareUrl);
    alert('تم نسخ الرابط.');
  });
})();
