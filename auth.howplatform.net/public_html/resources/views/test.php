<!--<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8" />
  <title>اختبار إصدارات التطبيق - HOW Platform</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    :root { --bg:#0f172a; --card:#111827; --fg:#e5e7eb; --muted:#94a3b8; --accent:#22d3ee; }
    body { margin:0; font-family:system-ui, Segoe UI, Tahoma; background:#0a0f1a; color:var(--fg); }
    .container { max-width:1100px; margin:24px auto; padding:0 16px; }
    h1{font-size:22px;margin:0 0 16px}
    .grid{display:grid;gap:16px;grid-template-columns:1fr}
    @media(min-width:900px){.grid{grid-template-columns:1fr 1fr}}
    .card{background:var(--card);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:16px}
    label{font-size:13px;color:var(--muted);display:block;margin:10px 0 6px}
    input, select{
      width:100%;padding:10px 12px;border-radius:10px;border:1px solid rgba(255,255,255,.08);
      background:#0b1220;color:var(--fg); outline:none;
    }
    button{
      padding:10px 14px;border:0;border-radius:10px;background:linear-gradient(135deg,#06b6d4,#2563eb);
      color:white;font-weight:700;cursor:pointer
    }
    button:disabled{opacity:.6;cursor:not-allowed}
    .muted{color:var(--muted);font-size:12px}
    .pill{display:inline-block;background:#0b1220;border:1px solid rgba(255,255,255,.08);padding:6px 10px;border-radius:999px;font-size:12px}
    .divider{height:1px;background:rgba(255,255,255,.08);margin:14px 0}
    pre{white-space:pre-wrap;word-break:break-word;background:#0b1220;padding:12px;border-radius:10px;border:1px solid rgba(255,255,255,.06)}
    a{color:#22d3ee;text-decoration:none}
    a:hover{text-decoration:underline}
    .row{display:flex;gap:10px;flex-wrap:wrap}
    .row > * { flex:1 }
    .hl{font-weight:700}
  </style>
</head>
<body>
  <div class="container">
    <h1>اختبار إصدار تطبيق الموبايل (Mobile App Release API)</h1>

    <div class="card">
      <div class="row">
        <label>API Base</label>
        <input id="base" type="text" value="https://auth.howplatform.net" />
        <div class="muted">مثال: https://auth.howplatform.net</div>
      </div>
      <div class="row" style="margin-top:10px">
        <button id="saveCtx">حفظ الإعداد</button>
        <span id="ctxMsg" class="muted"></span>
      </div>
    </div>

    <div class="grid" style="margin-top:16px">
      <div class="card">
        <div class="row" style="align-items:center">
          <div class="pill">GET</div>
          <div><strong>/api/mobile/app/latest</strong></div>
        </div>
        <div class="divider"></div>
        <div class="row">
          <div>
            <label>المنصة</label>
            <select id="platform">
              <option value="android">Android</option>
              <option value="ios">iOS</option>
              <option value="windows">Windows</option>
              <option value="mac">macOS</option>
              <option value="linux">Linux</option>
            </select>
          </div>
          <div>
            <label>القناة</label>
            <select id="channel">
              <option value="stable">Stable</option>
              <option value="beta">Beta</option>
            </select>
          </div>
        </div>
        <div class="row">
          <div>
            <label>الإصدار الحالي (version_name)</label>
            <input id="currName" type="text" placeholder="مثال: 1.0.3" />
          </div>
          <div>
            <label>الكود الحالي (version_code)</label>
            <input id="currCode" type="text" placeholder="مثال: 1003" />
          </div>
        </div>
        <div class="row" style="margin-top:10px">
          <button id="btnLatest">تنفيذ</button>
        </div>

        <div class="divider"></div>
        <div class="row">
          <div>
            <div class="muted">Download URL (يسجّل التحميل)</div>
            <div id="downloadUrlBox">—</div>
          </div>
          <div>
            <div class="muted">Storage URL (مباشر)</div>
            <div id="storageUrlBox">—</div>
          </div>
        </div>
        <div class="row" style="margin-top:8px">
          <button id="btnOpenDownload" disabled>فتح رابط التحميل (نافذة جديدة)</button>
          <button id="btnPingStorage" disabled>Ping للرابط المباشر</button>
        </div>
        <label>نتيجة /latest</label>
        <pre id="outLatest"></pre>
        <label>فحص Storage</label>
        <pre id="outPing"></pre>
      </div>

      <div class="card">
        <div class="row" style="align-items:center">
          <div class="pill">GET</div>
          <div><strong>/api/mobile/app/download/{id}</strong></div>
        </div>
        <div class="divider"></div>
        <div class="row">
          <label>اكتب ID الإصدار</label>
          <input id="releaseId" type="text" placeholder="مثال: 12" />
        </div>
        <div class="row" style="margin-top:10px">
          <button id="btnDownloadFetch">تنزيل برمجي (Fetch)</button>
          <button id="btnDownloadOpen">فتح رابط التحميل (نافذة جديدة)</button>
        </div>
        <label>النتيجة</label>
        <pre id="outDownload"></pre>
      </div>
    </div>

    <div class="card" style="margin-top:16px">
      <strong>ملاحظات:</strong>
      <ul class="muted">
        <li>استعمل <code>/api/mobile/app/latest</code> لجلب آخر إصدار منشور حسب المنصة والقناة.</li>
        <li><span class="hl">download_url</span>: مسار API الرسمي الذي يسجّل التحميل (وقد يعيد توجيهًا خارجيًا).</li>
        <li><span class="hl">storage_url</span>: رابط مباشر للملف إن كان متاحًا عبر التخزين العام.</li>
        <li>لو كان التخزين على نطاق خارجي (مثل hstgr.io)، قد يمنع CORS قراءة الرؤوس/الحجم عبر Fetch. في هذه الحالة افتح الرابط مباشرة.</li>
      </ul>
    </div>
  </div>

  <script>
    const $ = (id) => document.getElementById(id);

    // تحميل الإعداد
    (function loadCtx(){
      const base = localStorage.getItem('api_base');
      if(base) $('base').value = base;
    })();

    $('saveCtx').onclick = () => {
      localStorage.setItem('api_base', $('base').value.trim());
      $('ctxMsg').textContent = 'تم الحفظ ✔';
      setTimeout(()=> $('ctxMsg').textContent='', 1500);
    };

    function baseUrl(p){ return $('base').value.replace(/\/+$/,'') + p; }
    function busy(btn, on){ btn.disabled = !!on; btn.textContent = on ? '...جاري' : btn.dataset.txt; }
    async function show(outEl, resp){
      const ct = resp.headers.get('content-type') || '';
      let body;
      try {
        body = ct.includes('application/json') ? await resp.json() : await resp.text();
      } catch(e){ body = '(خطأ في قراءة الاستجابة)'; }
      outEl.textContent =
        (resp.ok ? '✅ OK' : `❌ ${resp.status} ${resp.statusText}`) + '\n\n' +
        (typeof body === 'string' ? body : JSON.stringify(body, null, 2));
    }

    // ===== Helpers for links =====
    function setLinkBox(el, url){
      if(!url){ el.innerHTML = '—'; return; }
      const a = document.createElement('a');
      a.href = url;
      a.target = '_blank';
      a.rel = 'noopener';
      a.textContent = url;
      el.innerHTML = '';
      el.appendChild(a);
    }

    // ========== /latest ==========
    (function(){
      const btn = $('btnLatest'); btn.dataset.txt = 'تنفيذ';
      btn.onclick = async ()=>{
        busy(btn,true);
        const params = new URLSearchParams({
          platform: $('platform').value,
          channel: $('channel').value,
          current_version: $('currName').value.trim(),
          current_code: $('currCode').value.trim()
        });
        try{
          const r = await fetch(baseUrl('/api/mobile/app/latest?'+params.toString()), { method:'GET' });
          const out = $('outLatest');
          const body = await r.json().catch(()=>null);
          out.textContent = (r.ok ? '✅ OK' : `❌ ${r.status} ${r.statusText}`) + '\n\n' + JSON.stringify(body, null, 2);

          // روابط + تهيئة أزرار
          const latest = body && body.latest ? body.latest : null;
          const dl = latest?.download_url || null;
          const st = latest?.storage_url || null;

          setLinkBox($('downloadUrlBox'), dl);
          setLinkBox($('storageUrlBox'), st);

          $('btnOpenDownload').disabled = !dl;
          $('btnPingStorage').disabled = !st;

          // ملء releaseId تلقائيًا
          if(latest?.id){ $('releaseId').value = latest.id; }

          // إتاحة تنزيل مباشر بزر آخر
          $('btnDownloadOpen').disabled = !latest?.id;

          // عند الضغط: فتح رابط التحميل في تبويب جديد (يسجّل + يعيد توجيه)
          $('btnOpenDownload').onclick = () => {
            if(!dl) return;
            const u = new URL(dl);
            u.searchParams.set('client_version','web-tester-2025.10.31');
            window.open(u.toString(), '_blank', 'noopener');
          };

          // Ping للرابط المباشر (قد يكون خارجي مع CORS)
          $('btnPingStorage').onclick = async () => {
            const outPing = $('outPing');
            outPing.textContent = '...جاري الفحص';
            try{
              // HEAD أولًا
              let resp = await fetch(st, { method:'HEAD' });
              const info = {
                ok: resp.ok,
                status: resp.status,
                url: resp.url || st,
                contentLength: resp.headers.get('content-length'),
                contentType: resp.headers.get('content-type'),
                cors: resp.type
              };
              // لو لم يسمح السيرفر بالـ HEAD، جرّب طلب جزئي صغير
              if(!resp.ok || !info.contentLength){
                try{
                  resp = await fetch(st, {
                    method: 'GET',
                    headers: { 'Range': 'bytes=0-0' }
                  });
                  info.ok = resp.ok;
                  info.status = resp.status;
                  info.url = resp.url || st;
                  info.contentLength = info.contentLength || resp.headers.get('content-length');
                  info.acceptRanges = resp.headers.get('accept-ranges');
                  info.cors = resp.type;
                }catch(e){}
              }
              outPing.textContent = JSON.stringify(info, null, 2) + (info.cors === 'opaque' ? "\n\n(ملاحظة: ردّ CORS opaque — افتح الرابط مباشرة للتحقق اليدوي.)" : '');
            }catch(e){
              outPing.textContent = '❌ خطأ: '+e.message;
            }
          };

        } catch(e){
          $('outLatest').textContent = '❌ خطأ: '+e.message;
        } finally { busy(btn,false); }
      };
    })();

    // ========== /download/{id} ==========
    (function(){
      // تنزيل برمجي عبر Fetch (يفشل أحيانًا مع CORS لو تم إعادة توجيه خارجي)
      const btnFetch = $('btnDownloadFetch'); btnFetch.dataset.txt = 'تنزيل برمجي (Fetch)';
      btnFetch.onclick = async ()=>{
        const out = $('outDownload');
        const id = $('releaseId').value.trim();
        if(!id){ out.textContent = '❌ أدخل releaseId'; return; }
        const url = baseUrl('/api/mobile/app/download/'+id+'?client_version=web-tester-2025.10.31');
        busy(btnFetch,true);
        try{
          const r = await fetch(url, { redirect:'follow' });
          // معلومات عن التحويل النهائي إن أمكن
          const meta = {
            ok: r.ok,
            status: r.status,
            redirected: r.redirected,
            finalUrl: r.url || url,
            type: r.type
          };
          // حاول حفظ الملف (قد يمنع CORS القراءة)
          try{
            const blob = await r.blob();
            const cd = r.headers.get('Content-Disposition') || '';
            const m = cd.match(/filename="?([^"]+)"?/);
            const filename = (m && m[1]) || 'download.apk';
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            link.click();
            out.textContent = '✅ تم تنزيل الملف برمجيًا.\n\n' + JSON.stringify(meta, null, 2);
          } catch(e){
            out.textContent = '⚠️ لم يُسمح بقراءة المحتوى (CORS؟). سيتم فتح رابط التحميل مباشرة.\n\n' + JSON.stringify(meta, null, 2);
            window.open(url, '_blank', 'noopener');
          }
        } catch(e){
          out.textContent = '❌ خطأ: '+e.message;
        } finally { busy(btnFetch,false); }
      };

      // فتح رابط التحميل مباشرة (أفضل خيار عندما يكون التخزين خارجيًا)
      const btnOpen = $('btnDownloadOpen'); btnOpen.dataset.txt = 'فتح رابط التحميل (نافذة جديدة)';
      btnOpen.onclick = ()=>{
        const id = $('releaseId').value.trim();
        if(!id){ $('outDownload').textContent = '❌ أدخل releaseId'; return; }
        const url = baseUrl('/api/mobile/app/download/'+id+'?client_version=web-tester-2025.10.31');
        window.open(url, '_blank', 'noopener');
        $('outDownload').textContent = 'تم فتح رابط التحميل: ' + url;
      };
    })();
  </script>
</body>
</html>
-->
<!--
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8" />
  <title>📱 اختبار المساقات وملفات الموبايل - HOW Diploma API</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    :root { --bg:#0f172a; --card:#111827; --fg:#e5e7eb; --muted:#94a3b8; --accent:#22d3ee; }
    body { margin:0; font-family:system-ui, Segoe UI, Tahoma; background:#0a0f1a; color:var(--fg); }
    .container { max-width:1200px; margin:24px auto; padding:0 16px; }
    h1{font-size:22px;margin:0 0 16px}
    .grid{display:grid;gap:16px;grid-template-columns:1fr}
    @media(min-width:900px){.grid{grid-template-columns:1fr 1fr}}
    .card{background:var(--card);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:16px}
    label{font-size:13px;color:var(--muted);display:block;margin:10px 0 6px}
    input,textarea{
      width:100%;padding:10px 12px;border-radius:10px;border:1px solid rgba(255,255,255,.08);
      background:#0b1220;color:var(--fg);outline:none;
    }
    button{
      padding:10px 14px;border:0;border-radius:10px;
      background:linear-gradient(135deg,#06b6d4,#2563eb);
      color:white;font-weight:700;cursor:pointer;
    }
    pre{white-space:pre-wrap;word-break:break-word;background:#0b1220;padding:12px;border-radius:10px;border:1px solid rgba(255,255,255,.06)}
    .muted{color:var(--muted);font-size:12px}
    .row{display:flex;gap:10px;flex-wrap:wrap}
    .row>*{flex:1}
    .pill{display:inline-block;background:#0b1220;border:1px solid rgba(255,255,255,.08);padding:6px 10px;border-radius:999px;font-size:12px}
    .divider{height:1px;background:rgba(255,255,255,.08);margin:14px 0}
  </style>
</head>
<body>
  <div class="container">
    <h1>🧪 اختبار واجهات الموبايل (Courses + Files API)</h1>


    <div class="card">
      <div class="row">
        <div><label>API Base</label><input id="base" type="text" value="https://auth.howplatform.net" /></div>
        <div><label>Bearer Token</label><input id="token" type="text" placeholder="أدخل التوكن" /></div>
      </div>
      <div class="row" style="margin-top:10px">
        <button id="saveCtx">حفظ الإعدادات</button>
        <span id="ctxMsg" class="muted"></span>
      </div>
    </div>

    <div class="grid" style="margin-top:16px">
      <div class="card">
        <div class="row"><div class="pill">GET</div><div><strong>/api/student/my-courses</strong></div></div>
        <div class="divider"></div>
        <button id="btnCourses">تنفيذ</button>
        <pre id="outCourses"></pre>
      </div>

      <div class="card">
        <div class="row"><div class="pill">GET</div><div><strong>/api/student/courses/{id}/content</strong></div></div>
        <div class="divider"></div>
        <input id="courseId" type="text" placeholder="مثال: 10" />
        <button id="btnContent">تنفيذ</button>
        <pre id="outContent"></pre>
      </div>

      <div class="card">
        <div class="row"><div class="pill">GET</div><div><strong>/api/student/courses/{id}/stats</strong></div></div>
        <div class="divider"></div>
        <input id="statsId" type="text" placeholder="مثال: 10" />
        <button id="btnStats">تنفيذ</button>
        <pre id="outStats"></pre>
      </div>

      <div class="card">
        <div class="row"><div class="pill">GET</div><div><strong>/api/student/tasks/{taskId}/context</strong></div></div>
        <div class="divider"></div>
        <input id="taskId" type="text" placeholder="Task ID" />
        <button id="btnTask">تنفيذ</button>
        <pre id="outTask"></pre>
      </div>
    </div>

    <h2 style="margin-top:40px">📁 اختبار ملفات الموبايل (Mobile Files API)</h2>
    <div class="grid">

      <div class="card">
        <div class="row"><div class="pill">GET</div><div><strong>/api/files/{id}</strong></div></div>
        <div class="divider"></div>
        <input id="fileIdShow" type="text" placeholder="File ID" />
        <button id="btnShow">عرض المعلومات</button>
        <pre id="outShow"></pre>
      </div>

      <div class="card">
        <div class="row"><div class="pill">GET</div><div><strong>/api/mobile/files/{id}/view</strong></div></div>
        <div class="divider"></div>
        <input id="fileIdView" type="text" placeholder="File ID" />
        <button id="btnView">عرض مباشر</button>
        <iframe id="previewFrame" style="width:100%;height:250px;border-radius:10px;margin-top:10px;background:#0b1220"></iframe>
      </div>

      <div class="card">
        <div class="row"><div class="pill">GET</div><div><strong>/api/mobile/files/{id}/download</strong></div></div>
        <div class="divider"></div>
        <input id="fileIdDown" type="text" placeholder="File ID" />
        <button id="btnDownload">تحميل الملف</button>
        <pre id="outDownload"></pre>
      </div>
    </div>
  </div>

  <script>
    const $ = id => document.getElementById(id);

    // تحميل الإعدادات السابقة
    (function(){
      const base=localStorage.getItem('api_base');
      const token=localStorage.getItem('api_token');
      if(base)$('base').value=base;
      if(token)$('token').value=token;
    })();

    $('saveCtx').onclick=()=>{
      localStorage.setItem('api_base',$('base').value.trim());
      localStorage.setItem('api_token',$('token').value.trim());
      $('ctxMsg').textContent='✅ تم الحفظ';
      setTimeout(()=>$('ctxMsg').textContent='',1500);
    };

    const baseUrl=p=>$('base').value.replace(/\/+$/,'')+p;
    const headers=()=> {
      const t=$('token').value.trim();
      const h={'Accept':'application/json'};
      if(t)h['Authorization']='Bearer '+t;
      return h;
    };
    async function show(out,resp){
      let txt;
      try{txt=await resp.text();}catch(e){txt='(لا يوجد جسم)'}
      out.textContent=`${resp.status} ${resp.statusText}\n\n${txt}`;
    }

    // ==== ملفات الموبايل ====
    $('btnShow').onclick=async()=>{
      const id=$('fileIdShow').value.trim();
      const r=await fetch(baseUrl(`/api/files/${id}`),{headers:headers()});
      await show($('outShow'),r);
    };
    $('btnView').onclick=()=>{
      const id=$('fileIdView').value.trim();
      $('previewFrame').src=baseUrl(`/api/mobile/files/${id}/view`);
    };
    $('btnDownload').onclick=()=>{
      const id=$('fileIdDown').value.trim();
      const url=baseUrl(`/api/mobile/files/${id}/download`);
      $('outDownload').textContent='⏬ جاري التحميل...';
      window.open(url,'_blank');
      $('outDownload').textContent='✅ تم فتح رابط التحميل: '+url;
    };

    // ==== المساقات ====
    $('btnCourses').onclick=async()=>{
      const r=await fetch(baseUrl('/api/student/my-courses'),{headers:headers()});
      await show($('outCourses'),r);
    };
    $('btnContent').onclick=async()=>{
      const id=$('courseId').value.trim();
      const r=await fetch(baseUrl(`/api/student/courses/${id}/content`),{headers:headers()});
      await show($('outContent'),r);
    };
    $('btnStats').onclick=async()=>{
      const id=$('statsId').value.trim();
      const r=await fetch(baseUrl(`/api/student/courses/${id}/stats`),{headers:headers()});
      await show($('outStats'),r);
    };
    $('btnTask').onclick=async()=>{
      const id=$('taskId').value.trim();
      const r=await fetch(baseUrl(`/api/student/tasks/${id}/context`),{headers:headers()});
      await show($('outTask'),r);
    };
  </script>
</body>
</html>
-->
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8" />
  <title>🧪 اختبار دعم الموبايل - HOW Platform (Requests + Chat)</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    :root { --bg:#0a0f1a; --card:#0f172a; --fg:#e5e7eb; --muted:#94a3b8; --accent:#22d3ee; --line:rgba(255,255,255,.08); }
    *{box-sizing:border-box}
    body { margin:0; font-family:system-ui, Segoe UI, Tahoma; background:var(--bg); color:var(--fg); }
    .container { max-width:1200px; margin:24px auto; padding:0 16px; }
    h1{font-size:22px;margin:0 0 8px}
    h2{font-size:18px;margin:24px 0 12px}
    .grid{display:grid;gap:16px;grid-template-columns:1fr}
    @media(min-width:900px){.grid{grid-template-columns:1fr 1fr}}
    .card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px}
    label{font-size:13px;color:var(--muted);display:block;margin:10px 0 6px}
    input,textarea,select{
      width:100%;padding:10px 12px;border-radius:10px;border:1px solid var(--line);
      background:#0b1220;color:var(--fg);outline:none;
    }
    textarea{min-height:90px;resize:vertical}
    button{
      padding:10px 14px;border:0;border-radius:10px;
      background:linear-gradient(135deg,#06b6d4,#2563eb);
      color:white;font-weight:700;cursor:pointer
    }
    pre{white-space:pre-wrap;word-break:break-word;background:#0b1220;padding:12px;border-radius:10px;border:1px solid var(--line);max-height:320px;overflow:auto}
    .muted{color:var(--muted);font-size:12px}
    .row{display:flex;gap:10px;flex-wrap:wrap}
    .row>*{flex:1}
    .pill{display:inline-block;background:#0b1220;border:1px solid var(--line);padding:6px 10px;border-radius:999px;font-size:12px}
    .divider{height:1px;background:var(--line);margin:14px 0}
    .kvs{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .kvs .pill{background:#0b1220}
  </style>
</head>
<body>
  <div class="container">
    <h1>📱 اختبار واجهات الدعم للموبايل (Service Requests + Chat)</h1>
    <div class="muted">أدخل عنوان الـ API (الدومين) وتوكن المصادقة ثم استخدم الأزرار أدناه.</div>

    <!-- إعدادات الاتصال -->
    <div class="card" style="margin-top:12px">
      <div class="row">
        <div><label>API Base</label><input id="base" type="text" value="https://auth.howplatform.net" /></div>
        <div><label>Bearer Token</label><input id="token" type="text" placeholder="أدخل التوكن" /></div>
      </div>
      <div class="row" style="margin-top:10px">
        <button id="saveCtx">حفظ الإعدادات</button>
        <span id="ctxMsg" class="muted"></span>
      </div>
      <div class="divider"></div>
      <div class="kvs">
        <span class="pill">GET</span><span class="muted">/api/mobile/support/meta</span>
        <span class="pill">POST</span><span class="muted">/api/mobile/support/requests</span>
        <span class="pill">GET</span><span class="muted">/api/mobile/support/requests</span>
        <span class="pill">GET</span><span class="muted">/api/mobile/support/requests/{id}</span>
        <span class="pill">POST</span><span class="muted">/api/mobile/support/requests/{id}/cancel</span>
        <span class="pill">GET</span><span class="muted">/api/mobile/support/top-meta</span>
        <span class="pill">GET</span><span class="muted">/api/mobile/support/requests/{id}/messages</span>
        <span class="pill">POST</span><span class="muted">/api/mobile/support/requests/{id}/messages</span>
        <span class="pill">PATCH</span><span class="muted">/api/mobile/support/requests/{id}/messages/{msgId}</span>
        <span class="pill">DELETE</span><span class="muted">/api/mobile/support/requests/{id}/messages/{msgId}</span>
        <span class="pill">POST</span><span class="muted">/api/mobile/support/requests/{id}/read-up-to</span>
        <span class="pill">GET</span><span class="muted">/api/mobile/support/unread-counters</span>
      </div>
    </div>

    <!-- ميتا وطلبات -->
    <h2>🗂️ الطلبات</h2>
    <div class="grid">
      <!-- Meta (Courses) -->
      <div class="card">
        <div class="row"><div class="pill">GET</div><div><strong>/api/mobile/support/meta</strong></div></div>
        <div class="divider"></div>
        <button id="btnMeta">جلب الميتا (المساقات)</button>
        <pre id="outMeta"></pre>
      </div>

      <!-- Create Request -->
      <div class="card">
        <div class="row"><div class="pill">POST</div><div><strong>/api/mobile/support/requests</strong></div></div>
        <div class="divider"></div>
        <label>Subject</label><input id="srSubject" type="text" placeholder="مثال: أحتاج مساعدة في مهمة الأسبوع 5" />
        <label>Description</label><textarea id="srDesc" placeholder="اشرح تفاصيل طلبك…"></textarea>
        <label>Course Offering ID (اختياري)</label><input id="srOffering" type="text" placeholder="offering_id" />
        <label>Files (اختياري)</label><input id="srFiles" type="file" multiple />
        <div class="row" style="margin-top:10px"><button id="btnCreateRequest">إنشاء الطلب</button></div>
        <pre id="outCreateRequest"></pre>
      </div>

      <!-- List Requests -->
      <div class="card">
        <div class="row"><div class="pill">GET</div><div><strong>/api/mobile/support/requests</strong></div></div>
        <div class="divider"></div>
        <div class="row">
          <div>
            <label>status</label>
            <select id="qStatus">
              <option value="">(الكل)</option>
              <option>pending</option>
              <option>in_progress</option>
              <option>resolved</option>
              <option>cancelled</option>
            </select>
          </div>
          <div><label>q</label><input id="qText" type="text" placeholder="بحث نصّي" /></div>
        </div>
        <div class="row">
          <div><label>offset</label><input id="qOffset" type="number" value="0"/></div>
          <div><label>limit</label><input id="qLimit" type="number" value="5"/></div>
        </div>
        <div class="row" style="margin-top:10px"><button id="btnListRequests">تنفيذ</button></div>
        <pre id="outListRequests"></pre>
      </div>

      <!-- Show + Cancel -->
      <div class="card">
        <div class="row"><div class="pill">GET/POST</div><div><strong>/api/mobile/support/requests/{id}</strong></div></div>
        <div class="divider"></div>
        <label>Request ID</label><input id="reqId" type="text" placeholder="رقم الطلب" />
        <div class="row" style="margin-top:10px">
          <button id="btnShowRequest">عرض التفاصيل</button>
          <button id="btnCancelRequest">إلغاء الطلب</button>
        </div>
        <pre id="outShowRequest"></pre>
      </div>
    </div>

    <!-- محادثة -->
    <h2>💬 المحادثة</h2>
    <div class="grid">
      <!-- Top Meta -->
      <div class="card">
        <div class="row"><div class="pill">GET</div><div><strong>/api/mobile/support/top-meta</strong></div></div>
        <div class="divider"></div>
        <button id="btnTopMeta">جلب ميتا الخطة</button>
        <pre id="outTopMeta"></pre>
      </div>

      <!-- List Messages -->
      <div class="card">
        <div class="row"><div class="pill">GET</div><div><strong>/api/mobile/support/requests/{id}/messages</strong></div></div>
        <div class="divider"></div>
        <label>Request ID</label><input id="msgReqId" type="text" placeholder="رقم الطلب" />
        <div class="row">
          <div><label>before_id</label><input id="beforeId" type="number" /></div>
          <div><label>limit</label><input id="msgLimit" type="number" value="10"/></div>
        </div>
        <div class="row" style="margin-top:10px"><button id="btnListMsgs">جلب الرسائل</button></div>
        <pre id="outListMsgs"></pre>
      </div>

      <!-- Send Message -->
      <div class="card">
        <div class="row"><div class="pill">POST</div><div><strong>/api/mobile/support/requests/{id}/messages</strong></div></div>
        <div class="divider"></div>
        <label>Request ID</label><input id="sendReqId" type="text" placeholder="رقم الطلب" />
        <label>Body (اختياري)</label><textarea id="sendBody" placeholder="نص الرسالة…"></textarea>
        <label>Files (اختياري)</label><input id="sendFiles" type="file" multiple />
        <div class="row" style="margin-top:10px"><button id="btnSendMsg">إرسال</button></div>
        <pre id="outSendMsg"></pre>
      </div>

      <!-- Edit / Delete -->
      <div class="card">
        <div class="row"><div class="pill">PATCH/DELETE</div><div><strong>/api/mobile/support/requests/{id}/messages/{msgId}</strong></div></div>
        <div class="divider"></div>
        <label>Request ID</label><input id="editReqId" type="text" />
        <label>Message ID</label><input id="editMsgId" type="text" />
        <label>New Body (للتعديل)</label><input id="editBody" type="text" />
        <div class="row" style="margin-top:10px">
          <button id="btnEditMsg">تعديل الرسالة</button>
          <button id="btnDeleteMsg">حذف الرسالة</button>
        </div>
        <pre id="outEditDelete"></pre>
      </div>

      <!-- Read Up To / Unread Counters -->
      <div class="card">
        <div class="row"><div class="pill">POST/GET</div><div><strong>read-up-to / unread-counters</strong></div></div>
        <div class="divider"></div>
        <label>Request ID</label><input id="readReqId" type="text" />
        <label>last_id (اختياري)</label><input id="readLastId" type="number" />
        <div class="row" style="margin-top:10px">
          <button id="btnReadUpTo">تعليم مقروء حتى</button>
          <button id="btnUnreadCounters">عدادات غير مقروء</button>
        </div>
        <pre id="outReadUnread"></pre>
      </div>
    </div>
  </div>

  <script>
    const $ = id => document.getElementById(id);

    // تحميل الإعدادات السابقة
    (function(){
      const base=localStorage.getItem('api_base');
      const token=localStorage.getItem('api_token');
      if(base)$('base').value=base;
      if(token)$('token').value=token;
    })();

    $('saveCtx').onclick=()=>{
      localStorage.setItem('api_base',$('base').value.trim());
      localStorage.setItem('api_token',$('token').value.trim());
      $('ctxMsg').textContent='✅ تم الحفظ';
      setTimeout(()=>$('ctxMsg').textContent='',1500);
    };

    const baseUrl=p=>$('base').value.replace(/\/+$/,'')+p;
    const authHeaders=()=> {
      const t=$('token').value.trim();
      const h={'Accept':'application/json'};
      if(t)h['Authorization']='Bearer '+t;
      return h;
    };
    async function show(out,resp){
      let txt;
      try{txt=await resp.text();}catch(e){txt='(لا يوجد جسم)'}
      out.textContent=`${resp.status} ${resp.statusText}\n\n${txt}`;
    }
    // طلب JSON
    async function jfetch(url, method='GET', body=null){
      const opts={ method, headers: {...authHeaders(), 'Content-Type':'application/json'} };
      if(body) opts.body=JSON.stringify(body);
      return fetch(url, opts);
    }
    // طلب FormData (ملفات)
    async function ffetch(url, method='POST', formData){
      const opts={ method, headers: authHeaders(), body: formData };
      return fetch(url, opts);
    }

    // ======== الطلبات ========
    $('btnMeta').onclick=async()=>{
      const r=await fetch(baseUrl('/api/mobile/support/meta'),{headers:authHeaders()});
      await show($('outMeta'),r);
    };

    $('btnCreateRequest').onclick=async()=>{
      const fd=new FormData();
      fd.append('subject',$('srSubject').value.trim());
      fd.append('description',$('srDesc').value.trim());
      const off=$('srOffering').value.trim();
      if(off) fd.append('course_offering_id',off);
      const files=$('srFiles').files;
      for(const f of files){ fd.append('files[]', f); }
      const r=await ffetch(baseUrl('/api/mobile/support/requests'),'POST',fd);
      await show($('outCreateRequest'),r);
    };

    $('btnListRequests').onclick=async()=>{
      const qs=new URLSearchParams();
      const st=$('qStatus').value;
      const q=$('qText').value.trim();
      const offset=$('qOffset').value||0;
      const limit=$('qLimit').value||5;
      if(st) qs.set('status',st);
      if(q) qs.set('q',q);
      qs.set('offset',offset);
      qs.set('limit',limit);
      const r=await fetch(baseUrl('/api/mobile/support/requests?'+qs.toString()),{headers:authHeaders()});
      await show($('outListRequests'),r);
    };

    $('btnShowRequest').onclick=async()=>{
      const id=$('reqId').value.trim();
      const r=await fetch(baseUrl(`/api/mobile/support/requests/${id}`),{headers:authHeaders()});
      await show($('outShowRequest'),r);
    };
    $('btnCancelRequest').onclick=async()=>{
      const id=$('reqId').value.trim();
      const r=await jfetch(baseUrl(`/api/mobile/support/requests/${id}/cancel`),'POST',{});
      await show($('outShowRequest'),r);
    };

    // ======== المحادثة ========
    $('btnTopMeta').onclick=async()=>{
      const r=await fetch(baseUrl('/api/mobile/support/top-meta'),{headers:authHeaders()});
      await show($('outTopMeta'),r);
    };

    $('btnListMsgs').onclick=async()=>{
      const id=$('msgReqId').value.trim();
      const qs=new URLSearchParams();
      const b=$('beforeId').value.trim();
      const lim=$('msgLimit').value||10;
      if(b) qs.set('before_id',b);
      if(lim) qs.set('limit',lim);
      const r=await fetch(baseUrl(`/api/mobile/support/requests/${id}/messages?`+qs.toString()),{headers:authHeaders()});
      await show($('outListMsgs'),r);
    };

    $('btnSendMsg').onclick=async()=>{
      const id=$('sendReqId').value.trim();
      const fd=new FormData();
      const body=$('sendBody').value.trim();
      if(body) fd.append('body', body);
      const files=$('sendFiles').files;
      for(const f of files){ fd.append('files[]', f); }
      const r=await ffetch(baseUrl(`/api/mobile/support/requests/${id}/messages`),'POST',fd);
      await show($('outSendMsg'),r);
    };

    $('btnEditMsg').onclick=async()=>{
      const id=$('editReqId').value.trim();
      const mid=$('editMsgId').value.trim();
      const body=$('editBody').value.trim();
      const r=await jfetch(baseUrl(`/api/mobile/support/requests/${id}/messages/${mid}`),'PATCH',{body});
      await show($('outEditDelete'),r);
    };
    $('btnDeleteMsg').onclick=async()=>{
      const id=$('editReqId').value.trim();
      const mid=$('editMsgId').value.trim();
      const r=await fetch(baseUrl(`/api/mobile/support/requests/${id}/messages/${mid}`),{method:'DELETE',headers:authHeaders()});
      await show($('outEditDelete'),r);
    };

    $('btnReadUpTo').onclick=async()=>{
      const id=$('readReqId').value.trim();
      const last=$('readLastId').value.trim();
      const body= last ? {last_id:parseInt(last,10)} : {};
      const r=await jfetch(baseUrl(`/api/mobile/support/requests/${id}/read-up-to`),'POST', body);
      await show($('outReadUnread'),r);
    };

    $('btnUnreadCounters').onclick=async()=>{
      const r=await fetch(baseUrl('/api/mobile/support/unread-counters'),{headers:authHeaders()});
      await show($('outReadUnread'),r);
    };
  </script>
</body>
</html>
