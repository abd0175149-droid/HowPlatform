@extends('layouts.app')
@section('title', $sess->title.' | HOW Tools')

@section('content')
<div class="kp-public">
  <h1>{{ $sess->title }}</h1>

  <canvas id="wheelCanvas" width="540" height="540" class="kp-wheel"></canvas>

  <div class="kp-actions" style="margin-top:1rem">
    <button id="btn-full" class="kp-btn">ملء الشاشة</button>
    <button id="btn-spin" class="kp-btn kp-btn--primary">دُر!</button>
  </div>
</div>

@push('scripts')
<script>
  const canvas = document.getElementById('wheelCanvas');
  const ctx    = canvas.getContext('2d');
  let items    = @json($items->pluck('label')->values());
  let angle=0, anim=null;

  function makeColors(n){
    const base=['#219ebc','#fd9e02','#8ecae6','#ffb703','#2a9d8f','#e76f51','#6a4c93','#ff6b6b','#4cc9f0','#90be6d','#f94144','#577590','#f3722c','#43aa8b','#f8961e','#277da1'];
    const out=[]; for(let i=0;i<n;i++) out.push(base[i%base.length]); return out;
  }
  function drawWheel(a=0){
    const n=items.length, colors=makeColors(n);
    const cx=canvas.width/2, cy=canvas.height/2, r=canvas.width/2-8;
    ctx.clearRect(0,0,canvas.width,canvas.height);
    for(let i=0;i<n;i++){
      const a0=a+i*(2*Math.PI/n), a1=a+(i+1)*(2*Math.PI/n);
      ctx.beginPath(); ctx.moveTo(cx,cy); ctx.arc(cx,cy,r,a0,a1); ctx.closePath();
      ctx.fillStyle=colors[i]; ctx.fill();
      ctx.save(); ctx.translate(cx,cy); ctx.rotate((a0+a1)/2);
      ctx.textAlign='center'; ctx.fillStyle='#fff'; ctx.font='16px sans-serif';
      ctx.fillText(items[i], r*0.65, 6); ctx.restore();
    }
    // pointer
    ctx.beginPath(); ctx.moveTo(cx,cy-r+6); ctx.lineTo(cx-10,cy-r-14); ctx.lineTo(cx+10,cy-r-14); ctx.closePath();
    ctx.fillStyle='#111'; ctx.fill();
  }
  drawWheel(0);

  document.getElementById('btn-full').addEventListener('click', ()=>{
    if (document.fullscreenElement) document.exitFullscreen();
    else canvas.requestFullscreen().catch(()=>{});
  });

  document.getElementById('btn-spin').addEventListener('click', ()=>{
    if(!items.length) return;
    // دوران محلي بسيط بدون حفظ نتيجة
    const n=items.length, segment=2*Math.PI/n;
    const targetIndex = Math.floor(Math.random()*n);
    const targetAngle = (Math.PI/2) - targetIndex*segment - segment/2;
    const spins = 6 * 2*Math.PI;
    const total = spins + ((targetAngle - angle) % (2*Math.PI));
    const duration = 8000; const t0 = performance.now();

    function frame(t){
      const p = Math.min(1,(t-t0)/duration);
      const e = 1 - Math.pow(1-p,3);
      const cur = angle + total*e;
      drawWheel(cur);
      if (p<1) { anim=requestAnimationFrame(frame); }
      else { angle = cur % (2*Math.PI); }
    }
    cancelAnimationFrame(anim); anim=requestAnimationFrame(frame);
  });
</script>
@endpush
@endsection
