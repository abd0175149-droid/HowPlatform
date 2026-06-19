{{-- resources/views/faq.blade.php --}}
@extends('layouts.main')

@push('styles')
  <style>
    /* تحسين تنسيق الأكورديون بالاعتماد على .card الموجودة */
    .faq-accordion { display: grid; gap: 14px; }
    .faq-item { border: 1px solid var(--glass-brd, rgba(255,255,255,.18)); border-radius: var(--border-radius, 16px); overflow: hidden; background: var(--glass-bg, rgba(255,255,255,.08)); }
    .faq-q {
      font-family: "Almarai", sans-serif;
      width: 100%; text-align: inherit; background: transparent; border: 0; padding: 1rem 1.1rem;
      color: #fff; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: space-between;
    }
    .faq-q span{font-size:20px;}
    .faq-q .iconfont-size: 1rem;opacity: .9;transition: transform .25s ease;transform-origin: center;}
    .faq-q[aria-expanded="true"] .icon{transform: rotate(180deg);}
    .faq-a {
      display: grid; grid-template-rows: 0fr; transition: grid-template-rows .25s ease; padding-inline: 1.1rem;
    }
    .faq-a > div { overflow: hidden; }
    .faq-item.open .faq-a { grid-template-rows: 1fr; padding-bottom: .95rem; }
    .faq-a p { color:#d6eaff; line-height:1.9; margin: .25rem 0; }
    .faq-a a { color: var(--accent-400, #ffbe34); text-decoration: none; }
    .faq-a a:hover { text-decoration: underline; }
  </style>
@endpush

@section('title', $seo['title'] ?? 'الأسئلة الشائعة')

@section('content')
  <div id="fxbg"></div>
  <div class="pulse-gradient" aria-hidden="true"></div>

  {{-- Hero --}}
  <section class="hero" style="padding:6rem 0 3rem">
    <div class="container hero-grid" style="grid-template-columns:1fr;">
      <div class="hero-copy" style="align-items:center;text-align:center">
        <h1>الأسئلة الشائعة</h1>
        <p class="lead" style="max-width:65ch;margin:auto">
          هذه الصفحة مخصّصة للأسئلة المتعلقة بالمنصّة الرئيسية <strong>كيف التعليمية</strong> فقط.
          للاستفسارات حول التسجيل أو الحسابات، يُرجى زيارة الصفحات المخصّصة في المنصّات الفرعية.
        </p>
      </div>
    </div>
  </section>
{{-- الأسئلة والأجوبة (خاصة بالمنصّة الرئيسية) --}}
<section>
  <div class="container">
    <div class="faq-accordion">
      @foreach($faq as $i => $item)
        <article class="faq-item card">
        <button class="faq-q" aria-expanded="false">
          <span>{{ $item['q'] }}</span>
          <i class="fas fa-chevron-down icon" aria-hidden="true"></i>
        </button>
          <div class="faq-a"><div><p>
            {!! $item['a'] !!}
          </p></div></div>
        </article>
      @endforeach
    </div>
  </div>
</section>

  {{-- دعوة للتواصل --}}
  <section class="statistics">
    <div class="container" style="text-align:center">
      <h2 class="section-title">لم تجد إجابتك؟</h2>
      <p class="lead" style="margin: 0 auto 1rem; max-width: 60ch;">
        إذا لم تجد إجابة لسؤالك هنا، تواصل معنا لنساعدك مباشرة.
      </p>
      <div class="cta-row" style="justify-content:center">
        <a href="{{ route('support') }}" class="btn btn-special">الدعم الفني</a>
        <a href="{{ route('contact') }}" class="btn btn-ghost">صفحة التواصل</a>
      </div>
    </div>
  </section>

  @push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const items = document.querySelectorAll('.faq-item');

    items.forEach((item) => {
      const btn  = item.querySelector('.faq-q');
      const icon = btn.querySelector('.icon'); // <i class="fas fa-chevron-down icon">

      const toggle = (force) => {
        const expanded = btn.getAttribute('aria-expanded') === 'true';
        const willOpen = (typeof force === 'boolean') ? force : !expanded;

        // إغلاق بقية العناصر (سلوك عنصر واحد مفتوح)
        if (willOpen) {
          items.forEach(i => {
            if (i !== item) {
              const b  = i.querySelector('.faq-q');
              const ic = b.querySelector('.icon');
              i.classList.remove('open');
              b.setAttribute('aria-expanded', 'false');
              if (ic) { ic.classList.remove('fa-chevron-up'); ic.classList.add('fa-chevron-down'); }
            }
          });
        }

        // فتح/إغلاق الحالي
        item.classList.toggle('open', willOpen);
        btn.setAttribute('aria-expanded', String(willOpen));

        // تبديل الأيقونة
        if (icon) {
          icon.classList.toggle('fa-chevron-up',   willOpen);
          icon.classList.toggle('fa-chevron-down', !willOpen);
        }
      };

      btn.addEventListener('click', () => toggle());
      // وصولية: دعم Enter/Space
      btn.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
      });
    });
  });
</script>


  @endpush
  @push('scripts')
  <script type="application/ld+json">
    {!! json_encode([
      '@context'    => 'https://schema.org',
      '@type'       => 'FAQPage',
      'mainEntity'  => $faqSchema,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
  </script>
@endpush

@endsection
