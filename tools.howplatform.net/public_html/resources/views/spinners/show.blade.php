{{-- resources/views/spinners/show.blade.php --}}
@extends('layouts.app')
@section('title', $session->title)

@section('sidebar')
  @include('partials.sidebar-spinners')
@endsection

@push('styles')
<style>
  .sp-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; }
  @media(max-width:920px){ .sp-grid{ grid-template-columns:1fr; } }

  .sp-card {
    background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:1.5rem;
  }
  .sp-title { font-size:1.1rem; font-weight:800; color:#0f172a; border-bottom:1px solid #e5e7eb; padding-bottom:.75rem; margin:0 0 1.25rem; }

  /* Buttons */
  .fb { display:inline-flex; align-items:center; gap:.4rem; border-radius:8px; font-weight:600; cursor:pointer; transition:.2s; border:none; }
  .fb-primary{ background:var(--primary,#219ebc); color:#fff; padding:.7rem 1.4rem; }
  .fb-primary:hover:not(:disabled){ background:#1a829c; }
  .fb-primary:disabled{ opacity:.6; cursor:not-allowed; }
  .fb-outline{ background:#f8fafc; color:#475569; border:1px solid #cbd5e1; padding:.7rem 1.2rem; text-decoration:none; }
  .fb-outline:hover{ background:#f1f5f9; }
  .fb-danger{ background:#fef2f2; color:#ef4444; border:1px solid #fca5a5; padding:.7rem 1.2rem; }
  .fb-danger:hover{ background:#fee2e2; }

  /* Wheel */
  #wheelWrap{ position:relative; width:min(480px,100%); aspect-ratio:1/1; margin:0 auto; }
  #wheelPtr{
    position:absolute; top:-14px; left:50%; transform:translateX(-50%);
    width:0; height:0; border-left:14px solid transparent; border-right:14px solid transparent;
    border-top:28px solid #ef4444; filter:drop-shadow(0 3px 5px rgba(0,0,0,.35)); z-index:5;
  }
  #wheelRotator{
    width:100%; height:100%; position:relative; border-radius:50%;
    overflow:hidden;
    box-shadow:0 0 0 7px var(--primary,#219ebc), 0 0 0 9px #e2e8f0;
    will-change:transform;
    /* transition set inline via JS */
  }
  #wheelCanvas{ position:absolute; inset:0; width:100%; height:100%; border-radius:50%; }

  /* Fullscreen */
  #fsWrap:fullscreen,#fsWrap:-webkit-full-screen {
    background:#0f172a; display:flex; flex-direction:column; align-items:center; justify-content:center;
    width:100vw; height:100vh;
  }
  #fsWrap:fullscreen #wheelWrap,#fsWrap:-webkit-full-screen #wheelWrap { width:min(560px,80vh); }
  #fsWrap:fullscreen #fsExitBtn,#fsWrap:-webkit-full-screen #fsExitBtn { display:flex !important; }
  #fsExitBtn{
    display:none; position:absolute; top:1.5rem; right:1.5rem;
    background:rgba(255,255,255,.12); color:#fff; border:1px solid rgba(255,255,255,.25);
    padding:.65rem 1.25rem; border-radius:8px; font-weight:600; cursor:pointer; gap:.4rem; align-items:center;
  }

  /* Winner overlay - works inside fsWrap for both normal + fullscreen */
  #winnerOverlay{
    position:fixed; inset:0; background:rgba(15,23,42,.65); backdrop-filter:blur(4px);
    display:none; align-items:center; justify-content:center; z-index:20000;
  }
  /* In fullscreen, fsWrap is the stacking context, overlay must cover it */
  #fsWrap:fullscreen #winnerOverlay,
  #fsWrap:-webkit-full-screen #winnerOverlay {
    position:fixed; z-index:20000;
  }
  #winnerOverlay.show{ display:flex; }
  .win-card{
    background:#fff; border-radius:20px; padding:2rem; width:min(440px,92%); text-align:center;
    animation:popIn .4s cubic-bezier(.175,.885,.32,1.275);
  }
  .win-label{ font-size:1rem; color:#64748b; margin-bottom:.5rem; }
  .win-name { font-size:2.5rem; font-weight:900; color:var(--primary,#0284c7); margin-bottom:1.5rem; }
  @keyframes popIn{ from{opacity:0;transform:scale(.8)} to{opacity:1;transform:scale(1)} }

  /* Items list */
  .item-row{
    display:flex; align-items:center; gap:.75rem; padding:.7rem 1rem;
    border:1px solid #e2e8f0; border-radius:8px; margin-bottom:.5rem;
    cursor:pointer; transition:background .15s;
  }
  .item-row:hover{ background:#f8fafc; }
  .item-row input[type=checkbox]{ width:1.2rem; height:1.2rem; accent-color:var(--primary,#219ebc); cursor:pointer; }

  /* Settings form */
  .sp-label{ display:block; font-weight:600; color:#334155; margin-bottom:.4rem; }
  .sp-select{ width:100%; padding:.7rem 1rem; border-radius:8px; border:1px solid #cbd5e1; background:#fff; }
  .sp-select:focus{ border-color:var(--primary,#219ebc); outline:none; }
  .radio-row{ display:flex; gap:1rem; }
  .radio-opt{
    display:flex; align-items:center; gap:.4rem; flex:1; justify-content:center;
    padding:.7rem; border:1px solid #cbd5e1; border-radius:8px; cursor:pointer;
  }
  .radio-opt:hover{ border-color:var(--primary,#219ebc); background:#f0f9ff; }

  /* QR Modal */
  #qrModal{
    position:fixed; inset:0; background:rgba(15,23,42,.65); backdrop-filter:blur(4px);
    display:none; align-items:center; justify-content:center; z-index:25000;
  }
  #qrModal.show{ display:flex; }
  .qr-card{
    background:#fff; border-radius:20px; padding:1.5rem; width:min(380px,92%);
    animation:popIn .35s cubic-bezier(.175,.885,.32,1.275);
  }
</style>
@endpush

@section('content')

{{-- QR Modal --}}
<div id="qrModal" onclick="if(event.target===this) closeQR()">
  <div class="qr-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
      <b style="font-size:1.1rem;color:#0f172a">مشاركة العجلة</b>
      <button class="fb fb-outline" onclick="closeQR()" style="padding:.4rem .8rem"><i class="fas fa-times"></i></button>
    </div>
    <div style="display:flex;flex-direction:column;align-items:center;gap:1rem">
      <div id="qrBox" style="padding:1rem;border:1px solid #e2e8f0;border-radius:12px;background:#fff;min-height:218px;display:flex;align-items:center;justify-content:center"></div>
      <div style="font-size:.8rem;color:#64748b;background:#f8fafc;padding:.5rem;border-radius:6px;word-break:break-all;width:100%">{{ url('/my/spinners/'.$session->id) }}</div>
      <div style="display:flex;gap:.5rem;width:100%">
        <button class="fb fb-outline" onclick="downloadQR()" style="flex:1;justify-content:center"><i class="fas fa-download"></i> تحميل</button>
        <button class="fb fb-primary" onclick="copyLink()" style="flex:1;justify-content:center"><i class="fas fa-copy"></i> نسخ الرابط</button>
      </div>
    </div>
  </div>
</div>

{{-- Page Header --}}
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
  <div style="display:flex;align-items:center;gap:1rem">
    <div style="background:#e0f2fe;color:#0284c7;width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem">
      <i class="fas fa-dharmachakra"></i>
    </div>
    <div>
      <h1 style="margin:0;font-size:1.5rem;font-weight:800;color:#0f172a">{{ $session->title }}</h1>
      <div style="font-size:.875rem;color:#64748b">اسحب الأسماء بعدالة وشفافية</div>
    </div>
  </div>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap">
    <button class="fb fb-outline" onclick="document.getElementById('fsWrap').requestFullscreen()"><i class="fas fa-expand"></i> ملء الشاشة</button>
    <button class="fb fb-outline" onclick="openQR()"><i class="fas fa-share-alt"></i> مشاركة</button>
    <form method="post" action="{{ route('user.spinners.destroy.post',$session->id) }}" onsubmit="return confirm('حذف العجلة نهائياً؟')" style="margin:0">
      @csrf
      <button type="submit" class="fb fb-danger"><i class="fas fa-trash"></i> حذف</button>
    </form>
  </div>
</div>

{{-- Main Grid --}}
<div class="sp-grid">

  {{-- Wheel Card --}}
  <div class="sp-card" style="display:flex;flex-direction:column;align-items:center;gap:1.5rem">
    <div id="fsWrap" style="width:100%;display:flex;flex-direction:column;align-items:center;gap:1.5rem;position:relative">

      <button id="fsExitBtn" onclick="document.exitFullscreen()" style="display:none">
        <i class="fas fa-compress"></i> خروج
      </button>

      {{-- Winner Overlay is INSIDE fsWrap so it shows in fullscreen mode too --}}
      <div id="winnerOverlay" onclick="if(event.target===this) closeWinner()">
        <div class="win-card">
          <div class="win-label">🎉 الفائز 🎉</div>
          <div class="win-name" id="winnerName">—</div>
          <button class="fb fb-primary" style="width:100%" onclick="closeWinner()">متابعة وإغلاق</button>
        </div>
      </div>

      <div id="wheelWrap">
        <div id="wheelPtr"></div>
        <div id="wheelRotator">
          <canvas id="wheelCanvas"></canvas>
        </div>
      </div>

      <div style="display:flex;gap:.75rem;justify-content:center">
        <button class="fb fb-primary" id="spinBtn" onclick="spinWheel()" style="font-size:1.05rem;padding:.8rem 2rem">
          <i class="fas fa-sync-alt" id="spinIcon"></i> لف العجلة
        </button>
        <button class="fb fb-outline" onclick="toggleSound()" id="soundBtn" style="min-width:48px;justify-content:center">
          <i class="fas fa-volume-up" id="soundIcon"></i>
        </button>
      </div>

      {{-- Hidden Audio --}}
      <audio id="audioSpin" preload="auto"><source src="{{ asset('assets/audio/spin.mp3') }}" type="audio/mpeg"></audio>
      <audio id="audioWin"  preload="auto"><source src="{{ asset('assets/audio/win.mp3') }}"  type="audio/mpeg"></audio>
    </div>
  </div>

  {{-- Settings + Items Card --}}
  <div style="display:flex;flex-direction:column;gap:1.5rem">

    {{-- Settings --}}
    <div class="sp-card">
      <p class="sp-title"><i class="fas fa-cog" style="color:#0284c7;margin-inline-end:.5rem"></i> إعدادات العجلة</p>

      <div style="margin-bottom:1.25rem">
        <label class="sp-label">قاعدة الاستبعاد بعد الفوز</label>
        <select class="sp-select" id="removeRule">
          <option value="none"            {{ $removeRule==='none'?'selected':'' }}>بدون حذف</option>
          <option value="remove_selected" {{ $removeRule==='remove_selected'?'selected':'' }}>احذف الفائز من العجلة</option>
          <option value="remove_others"   {{ $removeRule==='remove_others'?'selected':'' }}>احذف الجميع إلا الفائز</option>
        </select>
      </div>

      <div style="margin-bottom:1.25rem">
        <label class="sp-label">التأثيرات الصوتية</label>
        <div class="radio-row">
          <label class="radio-opt">
            <input type="radio" name="snd" value="1" id="sndOn" {{ $soundOn ? 'checked':'' }}> <span><i class="fas fa-volume-up" style="color:#0ea5e9"></i> مفعّل</span>
          </label>
          <label class="radio-opt">
            <input type="radio" name="snd" value="0" id="sndOff" {{ !$soundOn ? 'checked':'' }}> <span><i class="fas fa-volume-mute" style="color:#94a3b8"></i> صامت</span>
          </label>
        </div>
      </div>

      <button class="fb fb-primary" onclick="saveSettings()" style="width:100%">
        <i class="fas fa-save"></i> حفظ الإعدادات
      </button>
    </div>

    {{-- Items --}}
    <div class="sp-card">
      <p class="sp-title"><i class="fas fa-users" style="color:#0284c7;margin-inline-end:.5rem"></i> الأسماء والعناصر</p>
      <div style="font-size:.85rem;color:#64748b;margin-bottom:1rem">فعّل أو عطّل الأسماء لإدراجها أو استبعادها من العجلة</div>

      <div id="itemsList" style="max-height:240px;overflow-y:auto;margin-bottom:1.25rem">
        @foreach($items as $it)
        <label class="item-row">
          <input type="checkbox" class="itm-chk" data-id="{{ $it->id }}" {{ $it->is_active ? 'checked':'' }} onchange="toggleItem(this)">
          <span style="font-weight:600;color:#334155">{{ $it->label }}</span>
        </label>
        @endforeach
      </div>

      <label class="sp-label">إضافة أسماء جديدة (كل اسم بسطر)</label>
      <textarea id="newNames" rows="3" class="sp-select" placeholder="اكتب اسمًا هنا..."></textarea>
      <button class="fb fb-outline" onclick="addItems()" style="width:100%;justify-content:center;margin-top:.75rem">
        <i class="fas fa-plus"></i> إضافة
      </button>
    </div>

  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

<script>
/* ──── Config ──── */
const CFG = {
  csrf:       @json(csrf_token()),
  spinMs:     3000,
  removeRule: @json($removeRule),
  soundOn:    {{ (int)$soundOn }},
  shareUrl:   @json(url('/my/spinners/'.$session->id)),
  endpoints: {
    spin:   @json(route('user.spinners.spin',$session->id)),
    toggle: @json(route('user.spinners.items.toggle',$session->id)),
    add:    @json(route('user.spinners.items.add',$session->id)),
    save:   @json(route('user.spinners.settings',$session->id)),
  },
};
let ITEMS = @json($items->map(fn($r)=>['id'=>$r->id,'label'=>$r->label,'active'=>(bool)$r->is_active])->values());
let totalAngle = 0;
let spinning   = false;
let soundOn    = !!CFG.soundOn;
let qrInstance = null;

/* ──── Canvas Wheel ──── */
const COLORS = [
  '#0ea5e9','#f59e0b','#10b981',
  '#e11d48','#8b5cf6','#64748b',
];

function activeItems(){ return ITEMS.filter(x=>x.active); }

function drawWheel(){
  const canvas = document.getElementById('wheelCanvas');
  if(!canvas) return;
  const ctx   = canvas.getContext('2d');
  const size  = canvas.offsetWidth || 480;
  canvas.width = canvas.height = size * 2; // HiDPI
  ctx.scale(2,2);
  const cx = size/2, cy = size/2, r = size/2 - 4;
  const items = activeItems();
  const n = Math.max(1, items.length);
  const slice = (2 * Math.PI) / n;

  items.forEach((it, i) => {
    const start = i * slice - Math.PI/2;
    const end   = start + slice;
    // Slice
    ctx.beginPath();
    ctx.moveTo(cx,cy);
    ctx.arc(cx,cy,r,start,end);
    ctx.closePath();
    ctx.fillStyle = COLORS[i % COLORS.length];
    ctx.fill();
    ctx.strokeStyle='rgba(255,255,255,0.3)';
    ctx.lineWidth=1;
    ctx.stroke();
    // Text
    ctx.save();
    ctx.translate(cx,cy);
    ctx.rotate(start + slice/2);
    ctx.textAlign='left';
    ctx.fillStyle='#fff';
    const fs = Math.max(10, Math.min(18, Math.floor(r * 0.2)));
    ctx.font=`bold ${fs}px system-ui,sans-serif`;
    ctx.shadowColor='rgba(0,0,0,.4)';
    ctx.shadowBlur=3;
    const label = it.label.length > 14 ? it.label.slice(0,13)+'…' : it.label;
    ctx.fillText(label, r*0.2, fs/3);
    ctx.restore();
  });

  // Center circle
  ctx.beginPath();
  ctx.arc(cx,cy,r*0.08,0,2*Math.PI);
  ctx.fillStyle='#fff';
  ctx.shadowColor='rgba(0,0,0,.2)';
  ctx.shadowBlur=6;
  ctx.fill();
}

/* ──── Spin ──── */
async function spinWheel(){
  if(spinning || activeItems().length === 0) return;
  spinning = true;
  const btn = document.getElementById('spinBtn');
  const ico = document.getElementById('spinIcon');
  btn.disabled = true;
  ico.classList.add('fa-spin');

  const res = await fetch(CFG.endpoints.spin, {method:'POST', headers:{'X-CSRF-TOKEN':CFG.csrf}});
  const j   = await res.json();
  if(!j.ok){ spinning=false; btn.disabled=false; ico.classList.remove('fa-spin'); alert(j.error||'خطأ'); return; }

  const winLabel = j.winner?.label || '—';
  const winId    = j.winner?.id;

  const items = activeItems();
  const n     = Math.max(1, items.length);
  const slice = 360 / n;
  const idx   = Math.max(0, items.findIndex(x=>x.id===winId || x.label===winLabel));

  // Target: place winning slice at pointer (top = 270° in CSS, 0° in our conic)
  const sliceMid   = idx * slice + slice/2;
  const targetRel  = (360 - sliceMid) % 360;
  const jitter     = (Math.random()-.5) * slice * 0.4;
  const target     = totalAngle + (6*360) + targetRel + jitter;

  // Apply spin via CSS transition
  const rotator = document.getElementById('wheelRotator');
  rotator.style.transition = `transform ${CFG.spinMs}ms cubic-bezier(0.2,0.8,0.2,1)`;
  rotator.style.transform  = `rotate(${target}deg)`;

  playAudio('audioSpin');

  // Wait for animation
  setTimeout(()=>{
    totalAngle = target; // Accumulate (never reset to 0)
    // Snap transition off to prevent reverse on next update
    rotator.style.transition = 'none';

    stopAudio('audioSpin');
    playAudio('audioWin');

    // Confetti
    if(typeof confetti==='function'){
      confetti({particleCount:200,spread:100,origin:{y:.5},colors:['#0284c7','#f59e0b','#10b981','#e11d48','#8b5cf6']});
    }

    // Show winner
    document.getElementById('winnerName').textContent = winLabel;
    document.getElementById('winnerOverlay').classList.add('show');

    spinning = false;
    btn.disabled = false;
    ico.classList.remove('fa-spin');

    // Store winner info for closeWinner()
    window._lastWinner = { id: winId, label: winLabel };

  }, CFG.spinMs + 100);
}

/* ──── Winner Close ──── */
function closeWinner(){
  document.getElementById('winnerOverlay').classList.remove('show');
  const rule = document.getElementById('removeRule').value;
  const w    = window._lastWinner;
  if(!w) return;

  if(rule === 'remove_selected'){
    ITEMS = ITEMS.map(x=>({ ...x, active: (x.id===w.id || x.label===w.label) ? false : x.active }));
  } else if(rule === 'remove_others'){
    ITEMS = ITEMS.map(x=>({ ...x, active: (x.id===w.id || x.label===w.label) ? x.active : false }));
  }

  window._lastWinner = null;
  renderItems();
  drawWheel();
}

/* ──── Items List ──── */
function renderItems(){
  const list = document.getElementById('itemsList');
  list.innerHTML = '';
  ITEMS.forEach(it=>{
    const lbl = document.createElement('label');
    lbl.className = 'item-row';
    lbl.innerHTML = `
      <input type="checkbox" class="itm-chk" data-id="${it.id}" ${it.active?'checked':''} onchange="toggleItem(this)">
      <span style="font-weight:600;color:#334155">${it.label}</span>
    `;
    list.appendChild(lbl);
  });
}

async function toggleItem(el){
  const id     = +el.dataset.id;
  const active = el.checked ? 1 : 0;
  const item   = ITEMS.find(x=>x.id===id);
  if(item) item.active = !!active;
  drawWheel();
  await fetch(CFG.endpoints.toggle,{
    method:'POST',
    headers:{'X-CSRF-TOKEN':CFG.csrf,'Content-Type':'application/json'},
    body:JSON.stringify({item_id:id, active})
  });
}

async function addItems(){
  const ta  = document.getElementById('newNames');
  const txt = ta.value.trim();
  if(!txt) return;
  const fd  = new FormData();
  fd.append('labels', txt);
  const res = await fetch(CFG.endpoints.add,{method:'POST',headers:{'X-CSRF-TOKEN':CFG.csrf},body:fd});
  const j   = await res.json();
  if(j.ok) location.reload();
}

/* ──── Settings ──── */
async function saveSettings(){
  const rule = document.getElementById('removeRule').value;
  const snd  = document.querySelector('input[name="snd"]:checked')?.value ?? '1';
  const res  = await fetch(CFG.endpoints.save,{
    method:'POST',
    headers:{'X-CSRF-TOKEN':CFG.csrf,'Content-Type':'application/json'},
    body:JSON.stringify({spin_ms: CFG.spinMs, sound_on: snd, remove_rule: rule})
  });
  const j = await res.json();
  if(j.ok){ alert('✅ تم حفظ الإعدادات!'); }
  else     { alert('❌ فشل الحفظ: '+JSON.stringify(j)); }
}

/* ──── Sound ──── */
function toggleSound(){
  soundOn = !soundOn;
  document.getElementById('soundIcon').className = soundOn ? 'fas fa-volume-up' : 'fas fa-volume-mute';
}
function playAudio(id){
  if(!soundOn) return;
  const a = document.getElementById(id);
  if(!a) return;
  try{ a.currentTime=0; a.play(); }catch(e){}
}
function stopAudio(id){
  const a = document.getElementById(id);
  if(!a) return;
  try{ a.pause(); }catch(e){}
}

/* ──── QR ──── */
function openQR(){
  document.getElementById('qrModal').classList.add('show');
  const box = document.getElementById('qrBox');
  box.innerHTML = '';
  qrInstance = null;
  if(typeof QRCode !== 'undefined'){
    qrInstance = new QRCode(box, {text: CFG.shareUrl, width:200, height:200});
  } else {
    box.textContent = 'خطأ في تحميل مكتبة QR';
  }
}
function closeQR(){ document.getElementById('qrModal').classList.remove('show'); }
function downloadQR(){
  const img = document.querySelector('#qrBox img, #qrBox canvas');
  if(!img) return;
  const a   = document.createElement('a');
  a.download = 'spinner-qr.png';
  a.href = img.tagName==='IMG' ? img.src : img.toDataURL('image/png');
  a.click();
}
async function copyLink(){
  await navigator.clipboard.writeText(CFG.shareUrl);
  alert('✅ تم نسخ الرابط!');
}

/* ──── Fullscreen Button Text Toggle ──── */
document.addEventListener('fullscreenchange',()=>{
  const btn = document.querySelector('[onclick*="requestFullscreen"]');
  if(!btn) return;
  btn.innerHTML = document.fullscreenElement
    ? '<i class="fas fa-compress"></i> تصغير'
    : '<i class="fas fa-expand"></i> ملء الشاشة';
});

/* ──── Init ──── */
window.addEventListener('load', ()=>{
  drawWheel();
  // Debounced redraw on resize
  let _rwt;
  new ResizeObserver(()=>{ clearTimeout(_rwt); _rwt=setTimeout(drawWheel,120); })
    .observe(document.getElementById('wheelWrap'));
});
</script>
@endpush
