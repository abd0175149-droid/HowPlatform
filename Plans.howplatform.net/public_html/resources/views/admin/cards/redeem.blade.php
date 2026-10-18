@extends('layouts.admin')
@section('title','تفعيل بطاقة لمستخدم | Plans')
@section('heading','تفعيل بطاقة لصالح مستخدم')

@section('content')
  <p><a href="{{ route('admin.cards.index') }}" class="text-decoration-none">&larr; رجوع للبطاقات</a></p>

  <div class="card mx-auto" style="max-width:620px;">
    <div class="card-body">
      <p class="text-muted small mb-3">أدخِل كود البطاقة وابحث عن المستخدم بالاسم أو الإيميل أو الهاتف (أو المعرّف)، ثم فعّل البطاقة لصالحه.</p>

      <form method="post" action="{{ route('admin.cards.redeem') }}">
        @csrf
        <div class="mb-3">
          <label class="form-label fw-bold">كود البطاقة</label>
          <input name="code" class="form-control" placeholder="XXXX-XXXX-XXXX" value="{{ old('code') }}" required autofocus>
        </div>

        <div class="mb-3 position-relative">
          <label class="form-label fw-bold">المستخدم</label>
          <input id="userSearch" class="form-control" autocomplete="off" placeholder="ابحث بالاسم / الإيميل / الهاتف / المعرّف">
          <input type="hidden" name="user_id" id="userId" required>
          <div id="userResults" class="list-group position-absolute w-100 shadow" style="z-index:30; display:none; max-height:260px; overflow:auto;"></div>
          <div id="userChosen" class="form-text"></div>
        </div>

        <button class="btn btn-brand w-100"><i class="fas fa-circle-check"></i> تفعيل البطاقة للمستخدم</button>
      </form>
    </div>
  </div>

  @push('scripts')
  <script>
  (function(){
    const inp = document.getElementById('userSearch');
    const box = document.getElementById('userResults');
    const hid = document.getElementById('userId');
    const chosen = document.getElementById('userChosen');
    if(!inp) return;
    let t=null;
    inp.addEventListener('input', function(){
      hid.value=''; chosen.textContent='';
      const q = inp.value.trim();
      clearTimeout(t);
      if(q.length<2){ box.style.display='none'; return; }
      t=setTimeout(async function(){
        try{
          const r = await fetch('{{ route('admin.users.search') }}?q='+encodeURIComponent(q), {headers:{'Accept':'application/json'}});
          const data = await r.json();
          if(!Array.isArray(data) || !data.length){ box.innerHTML='<div class="list-group-item small text-muted">لا نتائج</div>'; box.style.display='block'; return; }
          box.innerHTML = data.map(u=>`<button type="button" class="list-group-item list-group-item-action small" data-id="${u.id}" data-label="${(u.name||('مستخدم '+u.id))} — ${u.id}">
            <b>${u.name||'—'}</b> <span class="text-muted">${u.email||''} ${u.phone? ('· '+u.phone):''} · ${u.id}</span></button>`).join('');
          box.style.display='block';
          box.querySelectorAll('button').forEach(btn=>btn.addEventListener('click', function(){
            hid.value = btn.dataset.id;
            inp.value = btn.dataset.label;
            chosen.innerHTML = '<i class="fas fa-check text-success"></i> تم اختيار المستخدم رقم '+btn.dataset.id;
            box.style.display='none';
          }));
        }catch(e){ box.style.display='none'; }
      }, 300);
    });
    inp.addEventListener('change', function(){ if(/^\d+$/.test(inp.value.trim())) hid.value = inp.value.trim(); });
    document.addEventListener('click', e=>{ if(!box.contains(e.target) && e.target!==inp) box.style.display='none'; });
  })();
  </script>
  @endpush
@endsection
