{{-- resources/views/about.blade.php --}}
@extends('layouts.main')

@section('title', $seo['title'] ?? 'حول منصة كيف التعليمية')

@section('content')
  {{-- خلفية مؤثرة بنفس ستايل الهوم --}}
  <div id="fxbg"></div>
  <div class="pulse-gradient" aria-hidden="true"></div>

  <section class="hero" style="padding:6rem 0 3rem">
    <div class="container hero-grid" style="gap:2.5rem;">
      <div class="hero-copy" style="align-items: flex-start;">
        <h1>حول <span style="color:var(--accent-500)">منصة كيف التعليمية</span></h1>
        <p class="lead" style="max-width:60ch">
          كيف التعليمية — <strong>المنصة العربية الرائدة التي تُعيد صياغة مستقبل التعليم بالتقنية</strong>.
          نوفّر منظومة تعليمية متكاملة تجمع بين الحلول الرقمية والمحتوى العملي والخدمات المبتكرة.
          بدأنا رحلتنا بـ <strong>الدبلوم العالي لإعداد المعلمين</strong> و<strong>المكتبة الرقمية</strong>،
          ونواصل التوسع لبناء أكبر بيئة تعليمية عربية داعمة للمعلم والطالب والمؤسسة.
        </p>

        <div class="cta-row">
          <a href="{{ url('/') }}" class="btn btn-primary">الصفحة الرئيسية</a>
          <a href="#platforms" class="btn btn-ghost">منصاتنا التعليمية</a>
          <a href="#services" class="btn btn-special">خدماتنا في الأردن</a>
        </div>
      </div>

      <div class="blob" aria-hidden="true"></div>
    </div>
  </section>

  {{-- الرسالة والرؤية والقيم --}}
  <section>
    <div class="container">
      <h2 class="section-title">من نحن</h2>
      <div class="grid-3">
        <article class="card">
          <h3>رسالتنا</h3>
          <p>تقديم حلول تعليمية رقمية مبتكرة تدعم الطالب والمعلم والمؤسسة، وتضمن وصولًا سهلًا إلى موارد عالية الجودة وتجربة استخدام مرنة.</p>
        </article>
        <article class="card">
          <h3>رؤيتنا</h3>
          <p>أن نصبح المنصة التعليمية المرجعية الأولى في الأردن والعالم العربي، نوحّد حولها المحتوى والأدوات والخدمات في بيئة آمنة وذكية.</p>
        </article>
        <article class="card">
          <h3>قيمنا</h3>
          <p>الإتقان، الابتكار، الموثوقية، الشمولية، والالتزام بالمسؤولية المجتمعية.</p>
        </article>
      </div>
    </div>
  </section>

  {{-- منصاتنا --}}
  <section id="platforms">
    <div class="container">
      <h2 class="section-title">منصاتنا التعليمية</h2>
      <div class="grid-3">
        <a class="card" href="https://diploma.howplatform.net" rel="noopener">
          <h4>منصة الدبلوم العالي</h4>
          <p>نوفّر دعمًا شاملًا لطلبة الدبلوم العالي في التربية — قبل الخدمة وأثناءها — عبر شروحات، موارد، ومهام عملية تُسهّل النجاح وتطوّر الأداء.</p>
        </a>
        <a class="card" href="https://library.howplatform.net" rel="noopener">
          <h4>المكتبة الرقمية</h4>
          <p>أكبر مكتبة تعليمية رقمية عربية، تضم آلاف الملفات والخطط والنماذج والأدلة، مصمّمة لدعم المعلمين في تخطيط الدروس ورفع كفاءة الممارسة الصفية.</p>
        </a>
        <div class="card">
          <h4>منصات قادمة</h4>
          <p>نعمل باستمرار على تطوير منصات جديدة تخدم شرائح تعليمية متخصصة وتواكب احتياجات المستقبل.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- خدماتنا في الأردن --}}
  <section id="services">
    <div class="container">
      <h2 class="section-title">خدماتنا في الأردن</h2>
      <div class="grid-3">
        <div class="card">
          <h4>محتوى وتوجيه أكاديمي</h4>
          <p>ملخصات أكاديمية، نماذج مهام، وأدلة عملية تدعم الطلبة والمعلمين ضمن إطار أكاديمي منضبط.</p>
        </div>
        <div class="card">
          <h4>أدوات تعليمية تفاعلية</h4>
          <p>أدوات رقمية مبتكرة لتنظيم التعلّم الذاتي وإدارة الوقت، بواجهات عربية سهلة وبسيطة.</p>
        </div>
        <div class="card">
          <h4>دعم فني ومجتمعي</h4>
          <p>فريق دعم نشط يساند المستخدمين، ومجتمع تفاعلي يشارك الخبرات والتجارب التعليمية.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- فريق العمل --}}
  <section>
    <div class="container">
      <h2 class="section-title">فريقنا</h2>
      <p>فريق شاب، متخصص، وشغوف يجمع بين الخبرة التقنية والمعرفة التربوية لبناء حلول تعليمية حديثة تجمع بين البساطة والفاعلية والابتكار.</p>
    </div>
  </section>

  {{-- دعوة للفعل --}}
  <section class="statistics">
    <div class="container" style="text-align:center">
      <h2 class="section-title">ابدأ رحلتك معنا</h2>
      <p class="lead" style="margin: 0 auto 1rem; max-width: 60ch;">
        انضم إلى آلاف المعلمين والطلبة الذين اختاروا منصة <strong>كيف التعليمية</strong> ليعيشوا تجربة رقمية متكاملة تُعيد تعريف التعليم.
      </p>
      <div class="cta-row" style="justify-content:center">
        <a href="https://library.howplatform.net" class="btn btn-primary">استكشف المكتبة</a>
        <a href="https://diploma.howplatform.net" class="btn btn-ghost">ابدأ رحلة الدبلوم</a>
      </div>
    </div>
  </section>

  {{-- SEO JSON-LD --}}
  @push('scripts')
    <script type="application/ld+json">
      {
        "@context":"https://schema.org",
        "@type":"Organization",
        "name":"منصة كيف التعليمية | How Platform",
        "url":"{{ $seo['url'] ?? url('/about') }}",
        "logo":"{{ asset('assets/images/logo.png') }}",
        "sameAs":[]
      }
    </script>
  @endpush
@endsection
