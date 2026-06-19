@extends('layouts.app')
@section('title','نموذج المهمة V2 | HOW Tools')

@section('sidebar')
  <div class="sidebar-section">
    <a href="{{ route('tools.index') }}" class="sidebar-link">
      <i class="fas fa-arrow-right"></i> العودة لجميع الأدوات
    </a>
  </div>
  <div class="sidebar-section">
    <h3 class="sidebar-title">الأداة</h3>
    <div class="sidebar-link active" style="cursor:default">
      <i class="fas fa-file-alt"></i> نموذج المهمة V2
    </div>
  </div>
@endsection

@section('content')
<div class="page-header"><h1 class="page-title">نموذج المهمة — الإصدار 2</h1></div>

<div class="tool-page">
  <section class="card-like">
    <form id="ctxForm" class="form-grid" onsubmit="return false" autocomplete="off">
      <div class="grid-2">
        <div class="form-row">
          <label>الجامعة</label>
          <select id="university_id"></select>
        </div>
        <div class="form-row">
          <label>التخصص</label>
          <select id="specialty_id"></select>
        </div>
        <div class="form-row">
          <label>نوع المهمة</label>
          <select id="task_type_id"></select>
        </div>
        <div class="form-row">
          <label>المساق</label>
          <select id="course_id"></select>
        </div>
        <div class="form-row">
          <label>السنة الدراسية</label>
          <select id="academic_year_id"></select>
        </div>
        <div class="form-row">
          <label>الفصل</label>
          <select id="semester_id"></select>
        </div>
      </div>

      <div class="actions">
        <button id="btnLoadTpl" class="btn btn-primary" type="button">
          <i class="fas fa-magic"></i> جلب القالب
        </button>
        <button id="btnTogglePreview" class="btn btn-outline" type="button">
          <i class="fas fa-eye"></i> معاينة القالب PDF
        </button>
      </div>
    </form>
  </section>

  <section class="card-like">
    <h3 class="section-title">المدخلات</h3>
    <form id="dynForm" class="form-grid" onsubmit="return false"></form>

    <div class="form-row" style="margin-top:.5rem">
      <label class="inline">
        <input type="checkbox" id="remember_profile" /> احفظ معلوماتي للاستخدام لاحقًا
      </label>
    </div>

    <div class="actions">
      <button id="btnSubmit" class="btn btn-primary" type="button"><i class="fas fa-save"></i> حفظ</button>
      <button id="btnDownloadPdf" class="btn btn-outline" type="button"><i class="fas fa-file-pdf"></i> تحميل PDF</button>
    </div>
  </section>

  <section class="card-like preview-section" id="previewSection" hidden aria-hidden="true">
    <h3 class="section-title">معاينة القالب</h3>
    <div class="pdf-frame-wrap">
      <iframe id="pdfPreview" class="pdf-frame" src="" title="معاينة القالب"></iframe>
    </div>
  </section>
</div>

<style>
  .tool-page{display:flex;flex-direction:column;gap:1rem}
  .card-like{background:var(--surface);border:1px solid var(--border);border-radius:16px;box-shadow:var(--shadow);padding:1rem}
  .form-grid{display:flex;flex-direction:column;gap:1rem}
  .grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
  .form-row{display:flex;flex-direction:column;gap:.4rem}
  .form-row label{font-weight:800;color:var(--text-secondary);font-size:.95rem}
  .form-row input[type="text"], .form-row input[type="date"], .form-row select, .form-row textarea{
    padding:.6rem .75rem;border:1px solid var(--border);border-radius:10px;background:var(--surface-weak);outline:none
  }
  .hint{font-size:.9rem;color:var(--text-secondary);margin-top:.35rem}
  .actions{display:flex;gap:.6rem;justify-content:flex-start}
  .actions .btn{min-width:180px}
  .preview-section[hidden]{display:none}
  .section-title{margin:.25rem 0 .75rem;font-size:1.05rem;font-weight:900;color:var(--neutral-800)}
  .pdf-frame-wrap{border:1px solid var(--border); border-radius:16px; overflow:hidden; background:#000; box-shadow:var(--shadow-md); min-height:420px;}
  .pdf-frame{width:100%; height:75vh; min-height:420px; border:0; background:#fff;}
  @media (max-width:900px){ .grid-2{grid-template-columns:1fr} .actions{flex-direction:column} .actions .btn{width:100%} }
</style>

<script src="https://cdn.tiny.cloud/1/hw9i625ncdv18e1tqeql9kygavozx4v1q0wtjvmdrxw9mik9/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
(function(){
  'use strict';
  const $=(s,c=document)=>c.querySelector(s);
  const routeMeta   = @json(route('tools.assignform.v2.meta'));
  const routeSchema = @json(route('tools.assignform.v2.schema'));
  const routeSubmit = @json(route('tools.assignform.v2.submit'));
  const routeStamp  = @json(route('tools.assignform.v2.stamp'));
  const routePrev   = @json(route('tools.assignform.v2.preview'));

  const ctx = {
    university_id: $('#university_id'),
    specialty_id:  $('#specialty_id'),
    task_type_id:  $('#task_type_id'),
    course_id:     $('#course_id'),
    academic_year_id: $('#academic_year_id'),
    semester_id:      $('#semester_id'),
  };

  let META=null, CURRENT_TPL=null;

  // Load DDL + defaults
  async function loadMeta(){
    const r = await fetch(routeMeta); META = await r.json();
    fillSelect(ctx.university_id, META.universities);
    fillSelect(ctx.specialty_id,  META.specialties);
    fillSelect(ctx.task_type_id,  META.task_types);
    fillSelect(ctx.course_id,     META.courses);
    fillSelect(ctx.academic_year_id, META.years);
    fillSelect(ctx.semester_id,      META.semesters);

    // defaults
    const d = META.defaults || {};
    // لاحقاً: املأ حقول المدخلات الديناميكية بعد جلب القالب
  }

  function fillSelect(sel, items){
    sel.innerHTML = '<option value="">— اختر —</option>';
    (items||[]).forEach(it=>{
      const opt = document.createElement('option');
      opt.value = it.id; opt.textContent = it.name || it.label;
      sel.appendChild(opt);
    });
  }

  // Fetch Template
  async function fetchSchema(){
    const payload = {};
    Object.keys(ctx).forEach(k=>{ const v=ctx[k].value; if(v) payload[k]=parseInt(v,10); });
    const r = await fetch(routeSchema,{method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':@json(csrf_token())}, body:JSON.stringify(payload)});
    if (!r.ok){ const t=await r.text(); alert('لم يتم العثور على قالب لهذا السياق.'); return; }
    CURRENT_TPL = await r.json();
    renderForm(CURRENT_TPL.ui_schema, CURRENT_TPL.data_schema, META.defaults||{});
  }

  function initTiny(selector){
    tinymce.init({
      selector, directionality:'rtl', menubar:'file edit view insert format table',
      plugins:'lists table image link paste autoresize code',
      toolbar:'undo redo | blocks | bold italic underline | alignright aligncenter alignleft alignjustify | bullist numlist outdent indent | table image link | removeformat | code',
      images_upload_handler:(blobInfo)=>new Promise((res)=>{res('data:'+blobInfo.blob().type+';base64,'+blobInfo.base64());}),
      paste_data_images:true, autoresize_bottom_margin:20, min_height:260,
      content_style:`body { font-family: "Amiri","Arial",sans-serif; font-size: 15px; line-height: 1.9 } table{border-collapse:collapse;width:100%} table,th,td{border:1px solid #999} th,td{padding:.45rem .55rem} img{max-width:100%;height:auto} ul,ol{margin:0 0 0 1.25rem} h1,h2,h3,h4{margin:.6rem 0 .35rem} a{color:#0d6efd;text-decoration:underline}`
    });
  }

  function destroyTiny(){
    (tinymce.editors||[]).forEach(ed=>{ try{ ed.remove(); }catch(e){} });
  }

  function buildFieldHTML(f, defaults){
    const name=f.name, label=f.label||name, type=(f.type||'text').toLowerCase();
    const help=f.help_text?`<small class="hint">${f.help_text}</small>`:'';
    const defVal = defaults?.[name] ?? '';
    if (type==='text')   return `<label for="${name}">${label}</label><input id="${name}" name="${name}" type="text" value="${escapeHtml(defVal)}">${help}`;
    if (type==='date')   return `<label for="${name}">${label}</label><input id="${name}" name="${name}" type="date">${help}`;
    if (type==='checkbox') return `<label class="inline"><input id="${name}" name="${name}" type="checkbox"> ${label}</label>${help}`;
    if (type==='select'){
      const id=name, opts=f.options||{};
      const src = opts.source||'';
      if (src.startsWith('diploma.')){
        // سنملأه من META لاحقاً
        return `<label for="${id}">${label}</label><select id="${id}" name="${id}"></select>${help}`;
      }
      const stat = (opts.static||[]).map(v=>`<option value="${escapeHtml(v)}">${escapeHtml(v)}</option>`).join('');
      return `<label for="${id}">${label}</label><select id="${id}" name="${id}"><option value="">— اختر —</option>${stat}</select>${help}`;
    }
    if (type==='richtext'){
      return `<label for="${name}">${label}</label><textarea id="${name}" name="${name}"></textarea>${help}`;
    }
    return `<label for="${name}">${label}</label><input id="${name}" name="${name}" type="text">${help}`;
  }

  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m])); }

  function renderForm(ui, data, defaults){
    const host = $('#dynForm'); destroyTiny(); host.innerHTML='';
    const fields = (data.fields||[]).reduce((map,f)=>{ map[f.name]=f; return map; },{});
    // sections
    const sections = (ui.sections||[]).sort((a,b)=>(a.order_index||0)-(b.order_index||0));
    const uiFields = (ui.fields||[]);

    sections.forEach(sec=>{
      const box=document.createElement('div'); box.className='form-grid'; if (sec.title){ const h=document.createElement('h3'); h.className='section-title'; h.textContent=sec.title; host.appendChild(h); }
      host.appendChild(box);
      uiFields.filter(ff=>ff.section_id===sec.id).sort((a,b)=>(a.order_index||0)-(b.order_index||0)).forEach(ff=>{
        const f = fields[ff.name] || {name:ff.name,label:ff.name,type:ff.type||'text'};
        const row=document.createElement('div'); row.className='form-row'; row.innerHTML = buildFieldHTML({...f, ...ff}, defaults);
        box.appendChild(row);
      });
    });

    // مصادر DDL من META
    (ui.fields||[]).forEach(ff=>{
      if ((ff.type||'').toLowerCase()!=='select') return;
      const src = (ff.options||{}).source||'';
      const el = document.getElementById(ff.name); if (!el) return;
      if (src==='diploma.specialties')  fillSelectFrom(el, META.specialties);
      if (src==='diploma.task_types')   fillSelectFrom(el, META.task_types);
      if (src==='diploma.courses')      fillSelectFrom(el, META.courses);
    });

    // TinyMCE
    (ui.fields||[]).filter(ff=>(ff.type||'').toLowerCase()==='richtext').forEach(ff=>initTiny('#'+ff.name));
  }
  function fillSelectFrom(sel, items){ sel.innerHTML='<option value="">— اختر —</option>'; (items||[]).forEach(it=>{ const o=document.createElement('option'); o.value=it.name||it.label||it.id; o.textContent=it.name||it.label; sel.appendChild(o); }); }

  // جمع القيم من النموذج الديناميكي
  function collectValues(){
    const values={};
    document.querySelectorAll('#dynForm [name]').forEach(el=>{
      if (el.type==='checkbox') values[el.name]=el.checked?1:0;
      else if (tinymce.get(el.id)) values[el.name]=tinymce.get(el.id).getContent();
      else values[el.name]=el.value;
    });
    return values;
  }

  // Preview
  const previewSec = $('#previewSection'), previewIF = $('#pdfPreview');
  function togglePreview(){
    const uni = ctx.university_id.value || '';
    const url = routePrev + (uni?('?university='+encodeURIComponent('jordan')):''); // يمكنك التخصيص
    if (previewSec.hasAttribute('hidden')){
      previewIF.src = url + '#zoom=page-fit';
      previewSec.removeAttribute('hidden'); previewSec.setAttribute('aria-hidden','false');
    } else {
      previewSec.setAttribute('hidden','true'); previewSec.setAttribute('aria-hidden','true');
    }
  }

  // Submit
  async function doSubmit(){
    if (!CURRENT_TPL){ alert('رجاءً اجلب القالب أولاً.'); return; }
    const payload = { template_id: CURRENT_TPL.template_id, remember_profile: $('#remember_profile').checked };
    Object.keys(ctx).forEach(k=>{ if(ctx[k].value) payload[k]=parseInt(ctx[k].value,10); });
    Object.assign(payload, collectValues());

    const r = await fetch(routeSubmit,{method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':@json(csrf_token())}, body:JSON.stringify(payload)});
    const j = await r.json(); if (!r.ok || !j.ok){ alert('فشل الحفظ.'); return; }
    alert('تم الحفظ بنجاح.');
  }

  // Stamp PDF
  async function doStamp(){
    if (!CURRENT_TPL){ alert('رجاءً اجلب القالب أولاً.'); return; }
    const values = collectValues();
    const form = document.createElement('form'); form.method='POST'; form.action=routeStamp; form.target='_blank';
    const add=(k,v)=>{ const i=document.createElement('input'); i.type='hidden'; i.name=k; i.value=v; form.appendChild(i); };

    add('_token', @json(csrf_token()));
    add('template_id', CURRENT_TPL.template_id);
    Object.keys(ctx).forEach(k=>{ if(ctx[k].value) add(k, ctx[k].value); });
    Object.entries(values).forEach(([k,v])=>{
      if (tinymce.get(k)) add(k, tinymce.get(k).getContent());
      else add(k, v);
    });
    document.body.appendChild(form); form.submit(); setTimeout(()=>form.remove(),500);
  }

  $('#btnLoadTpl').addEventListener('click', fetchSchema);
  $('#btnTogglePreview').addEventListener('click', togglePreview);
  $('#btnSubmit').addEventListener('click', doSubmit);
  $('#btnDownloadPdf').addEventListener('click', doStamp);

  loadMeta();
})();
</script>
@endsection
