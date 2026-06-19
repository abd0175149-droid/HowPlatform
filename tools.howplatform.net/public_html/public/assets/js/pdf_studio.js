(function () {
  const root = document.getElementById('pdfstudio');
  if (!root) return;

  // ========= Helpers =========
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const alertArea = $('#alertArea');

  function showAlert(msg) {
    const d = document.createElement('div');
    d.className = 'alert';
    d.textContent = msg;
    alertArea.innerHTML = '';
    alertArea.appendChild(d);
    setTimeout(() => { if (d.parentNode === alertArea) alertArea.removeChild(d); }, 6000);
  }

  // ---- Preview helpers (data URL أو Base64 خام) ----
  function toImgSrc(preview) {
    if (!preview || typeof preview !== 'string') return null;
    if (preview.startsWith('data:') || preview.startsWith('http')) return preview;
    return 'data:image/png;base64,' + preview.replace(/^data:image\/\w+;base64,/, '');
  }
  function b64OrUrlImg(preview) {
    const src = toImgSrc(preview);
    if (!src) return null;
    const img = new Image();
    img.src = src;
    img.alt = 'Preview';
    img.style.maxWidth = '100%';
    img.style.maxHeight = '100%';
    return img;
  }

  function humanBytes(n) {
    if (!Number.isFinite(n)) return '-';
    const u = ['B', 'KB', 'MB', 'GB']; let i = 0;
    while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
    return (Math.round(n * 10) / 10) + ' ' + u[i];
  }

  function deriveName(src, ext, suffix = '') {
    const stem = (src || 'output').replace(/\.[^.]+$/, '');
    return stem + (suffix || '') + ext;
  }

  function parseRanges(str) {
    const out = []; if (!str) return out;
    for (const part of str.split(',')) {
      const p = part.trim(); if (!p) continue;
      if (p.includes('-')) {
        const [a, b] = p.split('-').map(x => parseInt(x.trim(), 10));
        if (Number.isFinite(a) && Number.isFinite(b) && a > 0 && b >= a) out.push({ start: a, end: b });
      } else {
        const n = parseInt(p, 10);
        if (Number.isFinite(n) && n > 0) out.push({ start: n, end: n });
      }
    }
    return out;
  }
  function ensureRangesValid(ranges, maxPage) {
    if (!ranges.length) return 'الرجاء تحديد نطاق واحد على الأقل';
    for (const r of ranges) { if (r.start < 1 || r.end > maxPage) return 'النطاق خارج عدد الصفحات'; }
    return null;
  }

  function ensureApi() {
    const base = (root.dataset.apiBase || '').replace(/\/$/, '');
    if (!base) throw new Error('لم يتم ضبط PDF_API_BASE في الخادم.');
    return base;
  }
  function apiKey() { return root.dataset.apiKey || ''; }

  function sameOrigin(u) {
    try { const x = new URL(u, location.href); return x.origin === location.origin; } catch { return false; }
  }
  function resolveUrl(u) {
    if (!u) return null;
    if (/^https?:\/\//i.test(u)) return u;
    return ensureApi() + (u.startsWith('/') ? u : '/' + u);
  }

  async function handleJson(r) {
    const ct = r.headers.get('content-type') || '';
    const txt = await r.text(); let data = null;
    if (/json/i.test(ct)) { try { data = txt ? JSON.parse(txt) : null; } catch { } }
    if (!r.ok) {
      const msg = data && (data.detail || data.message)
        ? (typeof data.detail === 'string' ? data.detail : JSON.stringify(data.detail))
        : ('HTTP ' + r.status);
      throw new Error(msg);
    }
    return data || {};
  }

  async function apiUpload(path, field, file) {
    const fd = new FormData(); fd.append(field, file);
    const r = await fetch(ensureApi() + path, { method: 'POST', body: fd });
    return handleJson(r);
  }
  async function apiUploadMultiple(path, files) {
    const fd = new FormData(); files.forEach(f => fd.append('files', f));
    const r = await fetch(ensureApi() + path, { method: 'POST', body: fd });
    return handleJson(r);
  }
  async function apiPost(path, payload) {
    const r = await fetch(ensureApi() + path, {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(payload)
    });
    return handleJson(r);
  }

  const firstDef = (...vals) => vals.find(v => v !== undefined && v !== null);
  function extractDownloadInfo(res) {
    const cands = [res, res?.result, res?.data, res?.file, res?.payload];
    let url = firstDef(...cands.map(o => o?.download_url), ...cands.map(o => o?.url), ...cands.map(o => o?.link), ...cands.map(o => o?.location));
    let name = firstDef(res?.output_filename, res?.filename, res?.name,
      res?.result?.output_filename, res?.result?.filename, res?.result?.name);
    let prev = firstDef(res?.preview, res?.result?.preview, res?.data?.preview);
    let bytes = firstDef(res?.size_bytes, res?.result?.size_bytes, res?.data?.size_bytes);
    if (!name && url) {
      try { const u = new URL(url, location.href); name = decodeURIComponent(u.pathname.split('/').pop() || '') || null; } catch { }
    }
    return { url, name, previewB64: prev || null, size_bytes: bytes || null };
  }

  // ---- Generic card (preview + meta + download) ----
  function dlLink(url, label = 'تحميل', filename = null) {
    const a = document.createElement('a');
    const abs = resolveUrl(url);
    a.href = abs || '#';
    a.target = '_blank';
    a.rel = 'noopener';
    a.textContent = label;
    a.className = 'btn primary';
    if (abs && sameOrigin(abs) && filename) a.setAttribute('download', filename);
    return a;
  }
  function fileCard({ title, subtitle, previewB64, previewUrl, meta = [], downloadUrl = null, downloadName = null }) {
    const card = document.createElement('div'); card.className = 'card';
    const th = document.createElement('div'); th.className = 'thumb';
    const img = b64OrUrlImg(previewUrl || previewB64);
    if (img) th.appendChild(img); else th.innerHTML = '<div class="muted">لا توجد معاينة</div>';

    const body = document.createElement('div'); body.className = 'body';
    const h = document.createElement('div'); h.innerHTML = '<strong>' + (title || 'ملف') + '</strong>'; body.appendChild(h);
    if (subtitle) { const s = document.createElement('div'); s.className = 'muted'; s.textContent = subtitle; body.appendChild(s); }
    meta.forEach(([k, v]) => {
      const kv = document.createElement('div'); kv.className = 'row';
      kv.innerHTML = '<span class="muted">' + k + '</span><span style="margin-inline-start:auto">' + v + '</span>';
      body.appendChild(kv);
    });
    const row = document.createElement('div'); row.className = 'row';
    if (downloadUrl) row.appendChild(dlLink(downloadUrl, 'تحميل', downloadName));
    body.appendChild(row);

    card.appendChild(th); card.appendChild(body);
    return card;
  }

  // ========= Busy UI =========
  function mountLoader(target, text = 'جارٍ المعالجة...') {
    target.innerHTML = '';
    const wrap = document.createElement('div');
    wrap.className = 'loader';
    wrap.innerHTML = `<div class="spinner" aria-hidden="true"></div><div>${text}</div>`;
    target.appendChild(wrap);
    return wrap;
  }

  // Progress controller: يبدأ من 0% ويتدرج حتى 90%، ويكتمل إلى 100% عند استدعاء done()
  function mountProgress(target, hint = 'يُرجى عدم إغلاق الصفحة') {
    const wrap = document.createElement('div');
    wrap.style.marginTop = '8px';
    wrap.innerHTML = `
      <div class="progress-bar"><div class="progress-fill" style="width:0%"></div></div>
      <div class="muted" style="margin-top:6px">${hint}</div>
    `;
    target.appendChild(wrap);
    const fill = wrap.querySelector('.progress-fill');
    let val = 0;
    let alive = true;

    // زيادات تدريجية مع تباطؤ كلما اقترب من 90%
    const timer = setInterval(() => {
      if (!alive) return;
      const delta = Math.max((90 - val) * 0.06, 0.5); // سtep ديناميكي
      val = Math.min(val + delta, 90);
      fill.style.width = val.toFixed(1) + '%';
    }, 180);

    return {
      set: (p) => { val = Math.max(0, Math.min(100, p)); fill.style.width = val + '%'; },
      done: () => { alive = false; clearInterval(timer); fill.style.width = '100%'; }
    };
  }

  // ===== Downloads (session only) =====
  const DL_KEY = 'pdfstudio:downloads';
  function getMyDownloads() { try { return JSON.parse(localStorage.getItem(DL_KEY) || '[]'); } catch { return []; } }
  function saveMyDownloads(arr) { localStorage.setItem(DL_KEY, JSON.stringify(arr || [])); }
  function addMyDownload(item) {
    if (!item || !item.download_url) return;
    const arr = getMyDownloads();
    arr.unshift({
      title: item.title || 'ملف',
      subtitle: item.subtitle || '',
      download_url: item.download_url,
      size_bytes: item.size_bytes || null,
      created_at: Date.now()
    });
    saveMyDownloads(arr.slice(0, 100));
  }
  function renderMyDownloads() {
    const host = $('#filesList'); if (!host) return;
    const arr = getMyDownloads();
    host.innerHTML = '';
    if (!arr.length) { host.innerHTML = '<div class="alert">لا توجد تنزيلات لهذه الجلسة بعد.</div>'; return; }
    arr.forEach(it => {
      const meta = [];
      if (it.size_bytes) meta.push(['الحجم', humanBytes(it.size_bytes)]);
      meta.push(['الوقت', new Date(it.created_at).toLocaleString()]);
      host.appendChild(fileCard({
        title: it.title,
        subtitle: it.subtitle,
        meta,
        downloadUrl: it.download_url
      }));
    });
  }
  $('#btnRefreshFiles')?.addEventListener('click', renderMyDownloads);

  // Drop helpers
  function makeDrop(name, onFiles) {
    const dz = $(`.drop[data-drop="${name}"], .drop-zone[data-drop="${name}"]`);
    const pick = $(`#pick-${name}`);
    if (!dz || !pick) return;
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('dragover'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('dragover'));
    dz.addEventListener('drop', e => {
      e.preventDefault(); dz.classList.remove('dragover');
      const files = Array.from(e.dataTransfer.files || []);
      if (files.length) onFiles(files);
    });
    dz.addEventListener('click', () => pick.click());
    pick.addEventListener('change', () => {
      const files = Array.from(pick.files || []);
      if (files.length) onFiles(files);
      pick.value = '';
    });
  }

  // ========= OCR =========
  const OCRTool = (() => {
    let state = { fileId: null, fileName: '', pageCount: 0, fileSize: null, uploading: false, running: false };
    let bound = false;

    const els = {
      info: () => $('#ocrUploadInfo'),
      result: () => $('#ocrResult')
    };

    function reset() {
      state = { fileId: null, fileName: '', pageCount: 0, fileSize: null, uploading: false, running: false };
      const i = els.info(); const r = els.result();
      if (i) i.innerHTML = '';
      if (r) r.innerHTML = '—';
      const pick = $('#pick-ocr'); if (pick) pick.value = '';
    }

    function makeChipsRow({ name, size, pages }) {
      const chips = document.createElement('div');
      chips.className = 'chips';
      chips.innerHTML = `
        <span class="chip"><i class="fas fa-file"></i>${name || '-'}</span>
        <span class="chip"><i class="fas fa-weight-hanging"></i>${size ? humanBytes(size) : '-'}</span>
        <span class="chip"><i class="fas fa-copy"></i>${pages || '-' } صفحة</span>
      `;
      return chips;
    }
    function makeBtnGroup() {
      const g = document.createElement('div');
      g.className = 'btn-group';
      return g;
    }

    function bind() {
      if (bound) return; bound = true;

      makeDrop('ocr', async (files) => {
        const f = files[0]; if (!f || state.uploading || state.running) return;
        const infoBox = els.info(); const resBox = els.result();
        if (!infoBox || !resBox) return;

        reset();
        state.uploading = true;
        mountLoader(infoBox, `جارٍ رفع ${f.name} ...`);

        try {
          const res = await apiUpload('/ocr/upload', 'file', f);
          state.fileId    = firstDef(res?.file_id, res?.file?.file_id, res?.file?.id, res?.id) || null;
          state.pageCount = firstDef(res?.page_count, res?.file?.page_count, res?.pages) || 0;
          state.fileName  = f.name;
          state.fileSize  = firstDef(res?.file?.size_bytes, res?.size_bytes, f.size) || null;

          const preview = firstDef(res?.file?.preview, res?.preview);
          infoBox.innerHTML = '';

          // بطاقة المعاينة
          const upCard = fileCard({
            title: f.name,
            subtitle: 'PDF',
            previewUrl: preview
          });
          infoBox.appendChild(upCard);

          // تفاصيل جنب بعض
          infoBox.appendChild(makeChipsRow({ name: f.name, size: state.fileSize, pages: state.pageCount }));

          // أزرار جنب بعض مع أيقونات
          const actions = makeBtnGroup();
          const btnStart = document.createElement('button');
          btnStart.className = 'btn primary';
          btnStart.innerHTML = '<i class="fas fa-play"></i><span>بدء OCR</span>';

          const btnChange = document.createElement('button');
          btnChange.className = 'btn';
          btnChange.innerHTML = '<i class="fas fa-rotate-right"></i><span>تغيير الملف</span>';

          const btnRemove = document.createElement('button');
          btnRemove.className = 'btn';
          btnRemove.innerHTML = '<i class="fas fa-trash-alt"></i><span>إزالة</span>';

          btnStart.addEventListener('click', startOcr);
          btnChange.addEventListener('click', () => { $('#pick-ocr')?.click(); });
          btnRemove.addEventListener('click', reset);

          actions.appendChild(btnStart);
          actions.appendChild(btnChange);
          actions.appendChild(btnRemove);
          infoBox.appendChild(actions);

          resBox.innerHTML = '<div class="muted">—</div>';
        } catch (e) {
          infoBox.innerHTML = '';
          showAlert(e.message || 'فشل الرفع.');
        } finally {
          state.uploading = false;
        }
      });
    }

    async function startOcr() {
      if (!state.fileId || state.running) return;
      const resBox = els.result();
      if (!resBox) return;

      state.running = true;
      mountLoader(resBox, 'جارٍ تنفيذ OCR عبر Mistral ...');
      const prog = mountProgress(resBox, 'يُرجى عدم إغلاق الصفحة أثناء المعالجة');

      try {
        const payload = {
          file_id: state.fileId,
          output_filename: deriveName(state.fileName, '.docx')
        };
        const k = apiKey(); if (k) payload.api_key = k;

        const res = await apiPost('/ocr/commit', payload);
        const info = extractDownloadInfo(res);

        if (!info.url) {
          showAlert('لم يصل رابط التحميل من الخادم.');
          resBox.innerHTML = '—';
          return;
        }

        const meta = [];
        const pageCount = firstDef(res.page_count, res?.result?.page_count);
        const wordCount = firstDef(res.word_count, res?.result?.word_count);
        if (typeof pageCount !== 'undefined') meta.push(['الصفحات', pageCount]);
        if (typeof wordCount !== 'undefined') meta.push(['عدد الكلمات', wordCount]);

        const title = firstDef(res.output_filename, res.filename, info.name, 'نتيجة OCR');
        const card = fileCard({
          title,
          subtitle: 'DOCX',
          meta,
          downloadUrl: info.url,
          downloadName: info.name || title
        });

        resBox.innerHTML = '';
        resBox.appendChild(card);
        addMyDownload({ title, subtitle: 'DOCX', download_url: info.url, size_bytes: info.size_bytes });

        // نص مُختصر (إن وُجد)
        if (res.text_preview && typeof res.text_preview === 'string' && res.text_preview.trim().length) {
          const wrap = document.createElement('div'); wrap.style.marginTop = '10px';
          const toggle = document.createElement('button');
          toggle.className = 'btn'; toggle.innerHTML = '<i class="fas fa-align-left"></i> إظهار النص المستخرج';
          toggle.style.padding = '8px 12px';

          const pre = document.createElement('pre');
          pre.className = 'panel';
          pre.style.whiteSpace = 'pre-wrap';
          pre.style.direction = 'ltr';
          pre.style.marginTop = '6px';
          pre.style.display = 'none';
          pre.textContent = res.text_preview;

          toggle.addEventListener('click', () => {
            const open = pre.style.display !== 'none';
            pre.style.display = open ? 'none' : 'block';
            toggle.innerHTML = open
              ? '<i class="fas fa-align-left"></i> إظهار النص المستخرج'
              : '<i class="fas fa-eye-slash"></i> إخفاء النص المستخرج';
          });

          wrap.appendChild(toggle);
          wrap.appendChild(pre);
          resBox.appendChild(wrap);
        }
      } catch (e) {
        resBox.innerHTML = '';
        showAlert(e.message || 'فشل تنفيذ OCR.');
      } finally {
        state.running = false;
        // اكتمال التقدم
        try { prog.done(); } catch {}
      }
    }

    function init() { bind(); }
    function resetPublic() { reset(); }

    return { init, reset: resetPublic };
  })();

  // ========= Merge =========
  const MergeTool = (() => {
    let state = { files: [] };
    let bound = false;

    function reset() {
      state = { files: [] };
      const list = $('#mergeList'); if (list) list.style.display = 'none';
      const host = $('#mergeReorder'); if (host) host.innerHTML = '';
      const out = $('#mergeOut'); if (out) out.value = '';
      const result = $('#mergeResult'); if (result) result.innerHTML = '—';
      const pick = $('#pick-merge'); if (pick) pick.value = '';
    }

    function renderMergeReorder() {
      const host = $('#mergeReorder'); if (!host) return;
      host.innerHTML = '';
      state.files.forEach((it, idx) => {
        const el = document.createElement('div');
        el.className = 'row';
        el.style.cssText = 'padding:8px;border:1px dashed var(--border,#e5e7eb);border-radius:10px;background:#fff;cursor:grab;';
        el.draggable = true; el.dataset.index = idx;
        el.innerHTML = `<div class="muted">${idx + 1}.</div><div style="flex:1">${it.name || it.file_id || ''}</div><div class="muted">اسحب لإعادة الترتيب</div>`;
        el.addEventListener('dragstart', e => e.dataTransfer.setData('text/plain', idx + ''));
        el.addEventListener('dragover', e => e.preventDefault());
        el.addEventListener('drop', e => {
          e.preventDefault();
          const from = parseInt(e.dataTransfer.getData('text/plain'), 10);
          const to = idx; if (Number.isFinite(from) && from !== to) {
            const arr = state.files; const [m] = arr.splice(from, 1); arr.splice(to, 0, m);
            renderMergeReorder();
          }
        });
        host.appendChild(el);
      });
    }

    function bind() {
      if (bound) return; bound = true;

      makeDrop('merge', async (files) => {
        try {
          const res = await apiUploadMultiple('/pdf/merge/cards', Array.from(files));
          state.files = (res.files || []).map((it, i) => ({ ...it, name: files[i]?.name || it.file_id || it.id }));
          renderMergeReorder();
          $('#mergeList').style.display = 'block';
          $('#mergeOut').value = 'merged.pdf';
          const result = $('#mergeResult'); if (result) result.innerHTML = '—';
        } catch (e) { showAlert(e.message); }
      });

      $('#btnDoMerge')?.addEventListener('click', async () => {
        const ids = state.files.map(x => x.file_id || x.id).filter(Boolean);
        if (ids.length < 2) { showAlert('يتطلب ملفين على الأقل للدمج.'); return; }
        const resBox = $('#mergeResult'); if (!resBox) return;
        const btn = $('#btnDoMerge'); btn.disabled = true; btn.textContent = 'جارٍ الدمج...';
        mountLoader(resBox, 'جارٍ الدمج...');
        const prog = mountProgress(resBox);
        try {
          const res = await apiPost('/pdf/merge/commit', {
            file_ids: ids,
            output_filename: ($('#mergeOut').value || '').trim() || undefined
          });

          const info = extractDownloadInfo(res);
          if (!info.url) { showAlert('لم يصل رابط التحميل من الخادم.'); resBox.innerHTML = '—'; return; }

          const title = firstDef(res.output_filename, res.filename, info.name, 'ملف مدمج');
          const card = fileCard({
            title,
            subtitle: 'PDF',
            previewUrl: info.previewB64 || res.preview || null,
            downloadUrl: info.url,
            downloadName: info.name || title
          });

          resBox.innerHTML = ''; resBox.appendChild(card);
          addMyDownload({ title, subtitle: 'PDF', download_url: info.url });
        } catch (e) {
          resBox.innerHTML = '';
          showAlert(e.message);
        } finally {
          btn.disabled = false; btn.textContent = 'تنفيذ الدمج';
          try { prog.done(); } catch {}
        }
      });
    }

    function init() { bind(); }
    return { init, reset };
  })();

  // ========= Split =========
  const SplitTool = (() => {
    let state = { file_id: null, page_count: 0, name: '' };
    let bound = false;

    function reset() {
      state = { file_id: null, page_count: 0, name: '' };
      const cfg = $('#splitConfig'); if (cfg) cfg.style.display = 'none';
      const ranges = $('#ranges'); if (ranges) ranges.value = '';
      const sep = $('#splitSeparate'); if (sep) sep.checked = false;
      const prev = $('#splitPreviews'); if (prev) prev.innerHTML = '';
      const res = $('#splitResult'); if (res) res.innerHTML = '—';
      const pick = $('#pick-split'); if (pick) pick.value = '';
    }

    function bind() {
      if (bound) return; bound = true;

      makeDrop('split', async (files) => {
        const f = files[0]; if (!f) return;
        try {
          const res = await apiUpload('/pdf/split/upload', 'file', f);
          state.file_id = firstDef(res?.file?.file_id, res?.file_id, res?.id) || null;
          state.page_count = firstDef(res?.page_count, res?.file?.page_count, res?.pages) || 0;
          state.name = f.name;
          $('#splitConfig').style.display = 'block';
          showAlert(`تم الرفع: ${f.name} — الصفحات: ${state.page_count}`);
          $('#splitPreviews').innerHTML = '';
          $('#splitResult').innerHTML = '—';
        } catch (e) { showAlert(e.message); }
      });

      $('#btnPreviewPages')?.addEventListener('click', async () => {
        const ranges = parseRanges($('#ranges').value);
        const err = ensureRangesValid(ranges, state.page_count); if (err) { showAlert(err); return; }
        const pages = []; for (const r of ranges) { for (let i = r.start; i <= r.end; i++) pages.push(i); }
        const uniq = [...new Set(pages)].slice(0, 24);
        const host = $('#splitPreviews'); mountLoader(host, 'جارٍ إنشاء معاينات الصفحات ...');
        const prog = mountProgress(host, 'يتم تحضير المعاينات');
        try {
          const res = await apiPost('/pdf/split/page-preview', { file_id: state.file_id, pages: uniq });
          host.innerHTML = '';
          (res.previews || []).forEach((pv, i) => {
            host.appendChild(fileCard({ title: 'صفحة ' + uniq[i], previewUrl: pv }));
          });
        } catch (e) {
          host.innerHTML = '';
          showAlert(e.message);
        } finally {
          try { prog.done(); } catch {}
        }
      });

      $('#btnDoSplit')?.addEventListener('click', async () => {
        const ranges = parseRanges($('#ranges').value);
        const err = ensureRangesValid(ranges, state.page_count); if (err) { showAlert(err); return; }
        const resBox = $('#splitResult'); const btn = $('#btnDoSplit');
        btn.disabled = true; btn.textContent = 'جارٍ التقسيم...';
        mountLoader(resBox, 'جارٍ التقسيم...');
        const prog = mountProgress(resBox);
        try {
          const res = await apiPost('/pdf/split/commit', {
            file_id: state.file_id,
            ranges,
            separate_files: $('#splitSeparate').checked
          });

          resBox.innerHTML = '';
          const arr = res.results || (res.result ? [res.result] : []) || [];
          arr.forEach(r => {
            const info = extractDownloadInfo(r);
            if (!info.url) return;
            const title = state.name ? deriveName(state.name, '.pdf', `-part${r.index || ''}`) : (info.name || 'ملف مجزّأ');
            const card = fileCard({ title, previewUrl: info.previewB64 || r.preview || null, downloadUrl: info.url, downloadName: info.name || title });
            resBox.appendChild(card);
            addMyDownload({ title, subtitle: 'PDF', download_url: info.url });
          });
          if (!arr.length) resBox.innerHTML = '—';
        } catch (e) {
          resBox.innerHTML = '';
          showAlert(e.message);
        } finally {
          btn.disabled = false; btn.textContent = 'تنفيذ التقسيم';
          try { prog.done(); } catch {}
        }
      });
    }

    function init() { bind(); }
    return { init, reset };
  })();

  // ========= Compress =========
  const CompressTool = (() => {
    let state = { file_id: null, name: '' };
    let bound = false;

    function reset() {
      state = { file_id: null, name: '' };
      const cfg = $('#compressConfig'); if (cfg) cfg.style.display = 'none';
      const out = $('#compressOut'); if (out) out.value = '';
      const result = $('#compressResult'); if (result) result.innerHTML = '—';
      const pick = $('#pick-compress'); if (pick) pick.value = '';
      const lvl = $('#compressLevel'); if (lvl) lvl.value = 'medium';
    }

    function bind() {
      if (bound) return; bound = true;

      makeDrop('compress', async (files) => {
        const f = files[0]; if (!f) return;
        try {
          const res = await apiUpload('/pdf/compress/upload', 'file', f);
          state.file_id = firstDef(res?.file?.file_id, res?.file_id, res?.id) || null;
          state.name = f.name;
          $('#compressOut').value = deriveName(f.name, '.pdf', '-compressed');
          $('#compressConfig').style.display = 'block';
          $('#compressResult').innerHTML = '—';
        } catch (e) { showAlert(e.message); }
      });

      $('#btnDoCompress')?.addEventListener('click', async () => {
        const btn = $('#btnDoCompress'); const resBox = $('#compressResult');
        btn.disabled = true; btn.textContent = 'جارٍ الضغط...';
        mountLoader(resBox, 'جارٍ الضغط...');
        const prog = mountProgress(resBox);
        try {
          const res = await apiPost('/pdf/compress/commit', {
            file_id: state.file_id,
            level: $('#compressLevel').value,
            output_filename: ($('#compressOut').value || '').trim() || undefined
          });

          const info = extractDownloadInfo(res);
          if (!info.url) { showAlert('لم يصل رابط التحميل من الخادم.'); resBox.innerHTML = '—'; return; }
          const stats = res.stats || {};
          const meta = [];
          if (stats.original_bytes) meta.push(['الحجم الأصلي', humanBytes(stats.original_bytes)]);
          if (stats.new_bytes) meta.push(['الحجم الجديد', humanBytes(stats.new_bytes)]);
          if (typeof stats.saved_bytes === 'number') meta.push(['التوفير', humanBytes(stats.saved_bytes) + ' (' + (stats.saved_percent || '') + '%)']);

          const title = firstDef(res.output_filename, res.filename, info.name, 'ملف مضغوط');
          const card = fileCard({
            title,
            subtitle: 'PDF',
            previewUrl: info.previewB64 || res.preview || null,
            meta,
            downloadUrl: info.url,
            downloadName: info.name || title
          });
          resBox.innerHTML = ''; resBox.appendChild(card);
          addMyDownload({ title, subtitle: 'PDF', download_url: info.url, size_bytes: stats.new_bytes || info.size_bytes });
        } catch (e) {
          resBox.innerHTML = '';
          showAlert(e.message);
        } finally {
          btn.disabled = false; btn.textContent = 'تنفيذ الضغط';
          try { prog.done(); } catch {}
        }
      });
    }

    function init() { bind(); }
    return { init, reset };
  })();

  // ========= Convert =========
  const ConvertTool = (() => {
    let state = { file_id: null, name: '' };
    let bound = false;

    function reset() {
      state = { file_id: null, name: '' };
      const cfg = $('#convertConfig'); if (cfg) cfg.style.display = 'none';
      const out = $('#convertOut'); if (out) out.value = '';
      const res = $('#convertResult'); if (res) res.innerHTML = '—';
      const pick = $('#pick-convert'); if (pick) pick.value = '';
    }

    function bind() {
      if (bound) return; bound = true;

      makeDrop('convert', async (files) => {
        const f = files[0]; if (!f) return;
        try {
          const res = await apiUpload('/convert/upload', 'file', f);
          state.file_id = firstDef(res?.file?.file_id, res?.file_id, res?.id) || null;
          state.name = f.name;
          $('#convertOut').value = deriveName(f.name, '.pdf');
          $('#convertConfig').style.display = 'block';
          $('#convertResult').innerHTML = '—';
        } catch (e) { showAlert(e.message); }
      });

      $('#btnDoConvert')?.addEventListener('click', async () => {
        const btn = $('#btnDoConvert'); const resBox = $('#convertResult');
        btn.disabled = true; btn.textContent = 'جارٍ التحويل...';
        mountLoader(resBox, 'جارٍ التحويل...');
        const prog = mountProgress(resBox);
        try {
          const res = await apiPost('/convert/commit', {
            file_id: state.file_id,
            output_filename: ($('#convertOut').value || '').trim() || undefined
          });

          const info = extractDownloadInfo(res);
          if (!info.url) { showAlert('لم يصل رابط التحميل من الخادم.'); resBox.innerHTML = '—'; return; }
          const title = firstDef(res.output_filename, res.filename, info.name, 'PDF جديد');
          const card = fileCard({
            title,
            subtitle: 'PDF',
            previewUrl: info.previewB64 || res.preview || null,
            downloadUrl: info.url,
            downloadName: info.name || title
          });
          resBox.innerHTML = ''; resBox.appendChild(card);
          addMyDownload({ title, subtitle: 'PDF', download_url: info.url });
        } catch (e) {
          resBox.innerHTML = '';
          showAlert(e.message);
        } finally {
          btn.disabled = false; btn.textContent = 'تنفيذ التحويل';
          try { prog.done(); } catch {}
        }
      });
    }

    function init() { bind(); }
    return { init, reset };
  })();

  // ========= Watermark =========
  const WatermarkTool = (() => {
    let state = { file_id: null, name: '' };
    let bound = false;

    function reset() {
      state = { file_id: null, name: '' };
      const cfg = $('#wmConfig'); if (cfg) cfg.style.display = 'none';
      const txt = $('#wmText'); if (txt) txt.value = '';
      const op = $('#wmOpacity'); if (op) op.value = '0.2';
      const pos = $('#wmPosition'); if (pos) pos.value = 'diagonal';
      const fs = $('#wmFontSize'); if (fs) fs.value = '64';
      const out = $('#wmOut'); if (out) out.value = '';
      const res = $('#wmResult'); if (res) res.innerHTML = '—';
      const pick = $('#pick-watermark'); if (pick) pick.value = '';
    }

    function bind() {
      if (bound) return; bound = true;

      makeDrop('watermark', async (files) => {
        const f = files[0]; if (!f) return;
        try {
          const res = await apiUpload('/pdf/watermark/upload', 'file', f);
          state.file_id = firstDef(res?.file?.file_id, res?.file_id, res?.id) || null;
          state.name = f.name;
          $('#wmOut').value = deriveName(f.name, '.pdf', '-wm');
          $('#wmConfig').style.display = 'block';
          $('#wmResult').innerHTML = '—';
        } catch (e) { showAlert(e.message); }
      });

      $('#btnWmPreview')?.addEventListener('click', async () => {
        const resBox = $('#wmResult');
        mountLoader(resBox, 'جارٍ إنشاء معاينة العلامة المائية ...');
        const prog = mountProgress(resBox, 'تحضير المعاينة');
        try {
          const p = {
            text: ($('#wmText').value || 'CONFIDENTIAL').trim(),
            opacity: parseFloat($('#wmOpacity').value) || 0.2,
            position: $('#wmPosition').value,
            font_size: parseInt($('#wmFontSize').value, 10) || 64,
            file_id: state.file_id
          };
          const res = await apiPost('/pdf/watermark/preview', p);
          const info = extractDownloadInfo(res);
          const card = fileCard({ title: 'معاينة', previewUrl: info.previewB64 || res.preview || null });
          resBox.innerHTML = ''; resBox.appendChild(card);
        } catch (e) {
          resBox.innerHTML = '';
          showAlert(e.message);
        } finally {
          try { prog.done(); } catch {}
        }
      });

      $('#btnWmCommit')?.addEventListener('click', async () => {
        const btn = $('#btnWmCommit'); const resBox = $('#wmResult');
        btn.disabled = true; btn.textContent = 'جارٍ الإنشاء...';
        mountLoader(resBox, 'جارٍ إنشاء الملف النهائي ...');
        const prog = mountProgress(resBox);
        try {
          const p = {
            text: ($('#wmText').value || 'CONFIDENTIAL').trim(),
            opacity: parseFloat($('#wmOpacity').value) || 0.2,
            position: $('#wmPosition').value,
            font_size: parseInt($('#wmFontSize').value, 10) || 64,
            file_id: state.file_id,
            output_filename: ($('#wmOut').value || '').trim() || undefined
          };
          const res = await apiPost('/pdf/watermark/commit', p);
          const info = extractDownloadInfo(res);
          if (!info.url) { showAlert('لم يصل رابط التحميل من الخادم.'); resBox.innerHTML = '—'; return; }
          const title = firstDef(res.output_filename, res.filename, info.name, 'PDF موسوم');
          const card = fileCard({
            title,
            previewUrl: info.previewB64 || res.preview || null,
            downloadUrl: info.url,
            downloadName: info.name || title
          });
          resBox.innerHTML = ''; resBox.appendChild(card);
          addMyDownload({ title, subtitle: 'PDF', download_url: info.url });
        } catch (e) {
          resBox.innerHTML = '';
          showAlert(e.message);
        } finally {
          btn.disabled = false; btn.textContent = 'إنشاء النهائي';
          try { prog.done(); } catch {}
        }
      });
    }

    function init() { bind(); }
    return { init, reset };
  })();

  // ========= Tool Manager =========
  const ToolManager = (() => {
    let current = null;
    const sections = $$('.section');

    function showSection(key) {
      sections.forEach(s => {
        const visible = (s.dataset.section || s.id) === key;
        s.style.display = visible ? 'block' : 'none';
        // دعم أقسام ذات id (من قالبك البديل)
        if (s.id === key) s.classList.add('active'); else s.classList.remove('active');
      });
      $$('.sidebar-link[data-target]').forEach(a => a.classList.toggle('active', a.dataset.target === key));
      $$('.tool-btn[data-target]').forEach(b => b.classList.toggle('active', b.dataset.target === key));

      // تفريغ السابقة
      if (current && current !== key) {
        if (current === 'ocr') OCRTool.reset();
        if (current === 'merge') MergeTool.reset();
        if (current === 'split') SplitTool.reset();
        if (current === 'compress') CompressTool.reset();
        if (current === 'convert') ConvertTool.reset();
        if (current === 'watermark') WatermarkTool.reset();
      }

      // تهيئة الحالية
      if (key === 'ocr') OCRTool.init();
      if (key === 'merge') MergeTool.init();
      if (key === 'split') SplitTool.init();
      if (key === 'compress') CompressTool.init();
      if (key === 'convert') ConvertTool.init();
      if (key === 'watermark') WatermarkTool.init();
      if (key === 'files') renderMyDownloads();

      current = key;
      try { localStorage.setItem('pdfstudio:section', key); } catch { }
    }

    function bindNav() {
      $$('.sidebar-link[data-target]').forEach(a => a.addEventListener('click', e => { e.preventDefault(); showSection(a.dataset.target); }));
      $$('.tool-btn[data-target]').forEach(b => b.addEventListener('click', () => showSection(b.dataset.target)));
      let start = 'ocr';
      try { const saved = localStorage.getItem('pdfstudio:section'); if (saved) start = saved; } catch { }
      showSection(start);
    }

    return { showSection, bindNav };
  })();

  // Init
  try { ToolManager.bindNav(); }
  catch (e) { console.error(e); showAlert(e.message || 'حدث خطأ في تهيئة الأداة.'); }
})();
