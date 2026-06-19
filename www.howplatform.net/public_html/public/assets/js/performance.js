
// ======== Helpers ========
function toast(msg){ alert(msg); }
function q(sel,root=document){ return root.querySelector(sel); }
function qa(sel,root=document){ return Array.from(root.querySelectorAll(sel)); }

function openModal(sel){
  const m = typeof sel==='string' ? q(sel) : sel;
  m?.setAttribute('aria-hidden','false');
}
function closeModal(node){
  // يدعم الاستدعاء من أي عقدة داخل المودال أو بدون وسيط
  const m = node?.closest?.('.modal') ?? q('.modal[aria-hidden="false"]');
  m?.setAttribute('aria-hidden','true');
}

document.addEventListener('click', (e)=>{
  const opener = e.target.closest?.('[data-open]');
  if (opener) { openModal(opener.getAttribute('data-open')); return; }

  const closer = e.target.closest?.('[data-close]');
  if (closer) { closeModal(closer); return; }

  if (e.target.classList?.contains('modal')) {
    e.target.setAttribute('aria-hidden','true');
  }
});

// حالة تحميل بسيطة على الأزرار داخل أي فورم
function setFormBusy(form, busy=true){
  const btn = form.querySelector('button[type="submit"]');
  if(!btn) return;
  if(busy){
    btn.dataset._orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري المعالجة...';
  }else{
    btn.disabled = false;
    if(btn.dataset._orig){ btn.innerHTML = btn.dataset._orig; delete btn.dataset._orig; }
  }
}

// ======== إضافة أسبوع ========
const meta = document.querySelector('meta[name="csrf-token"]')?.content;

q('#addWeekForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.currentTarget;
  setFormBusy(form, true);
  try {
    const fd = new FormData(form);
    const res = await fetch('/api/performance/weeks/create', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': meta },
      body: fd
    });
    const data = await res.json();
    toast(data.message || 'تم');
    if (data.status === 'ok') {
      form.reset();
      closeModal(form);
      location.reload();
    }
  } catch (err) {
    toast('تعذر الإرسال');
  } finally {
    setFormBusy(form, false);
  }
});

// ======== إضافة مهمة ========
q('#addTaskForm')?.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const form = e.currentTarget;
  setFormBusy(form,true);
  try{
    const fd = new FormData(form);
    const res = await fetch('/api/performance/tasks/create', { method:'POST', body: fd });
    const data = await res.json();
    toast(data.message || 'تم');
    if(data.status==='ok'){ form.reset(); closeModal(form); location.reload(); }
  }catch(err){ toast('تعذر الإرسال'); }
  finally{ setFormBusy(form,false); }
});

// ======== إدخال أسماء الطلبة من TextArea ========
q('#addStudentsTextForm')?.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const form = e.currentTarget;
  setFormBusy(form, true);

  try{
    const namesText = q('#namesText').value || '';

    // Build JSON payload
    const payload = { names_text: namesText };

    // Headers
    const hdrs = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
    const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if(meta) hdrs['X-CSRF-TOKEN'] = meta;

    const res  = await fetch('/api/performance/students/import-text', {
      method: 'POST',
      headers: hdrs,
      body: JSON.stringify(payload)
    });

    const data = await res.json();

    if(!res.ok || data.status!=='ok'){
      toast(data.message || 'تعذر حفظ الأسماء');
      return;
    }

    toast(data.message || `تم حفظ ${data.added} اسمًا`);
    form.reset();
    closeModal(form);
    location.reload();
  }catch(err){
    toast('تعذر حفظ الأسماء');
  }finally{
    setFormBusy(form, false);
  }
});

