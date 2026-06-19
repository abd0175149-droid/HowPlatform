// nav toggle (RTL-safe)
(function(){
  const btn = document.querySelector('.nav-toggle');
  const nav = document.querySelector('.main-nav');
  if(!btn || !nav) return;
  btn.addEventListener('click', ()=>{
    const open = nav.classList.toggle('open');
    btn.setAttribute('aria-expanded', open?'true':'false');
    document.body.style.overflow = open ? 'hidden' : '';
  });
  document.addEventListener('click', (e)=>{
    if (!nav.classList.contains('open')) return;
    const inside = e.target.closest('.main-nav') || e.target.closest('.nav-toggle');
    if (!inside){ nav.classList.remove('open'); document.body.style.overflow=''; }
  });
})();

// reveal on view
(function(){
  const els = document.querySelectorAll('.reveal-up');
  const io = 'IntersectionObserver' in window ? new IntersectionObserver((entries)=>{
    entries.forEach(en=>{
      if(en.isIntersecting){ en.target.classList.add('visible'); io.unobserve(en.target); }
    });
  }, {threshold:.12}) : null;
  els.forEach(el=> io ? io.observe(el) : el.classList.add('visible'));
})();
