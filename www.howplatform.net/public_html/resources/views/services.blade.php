{{-- resources/views/services.blade.php --}}
@extends('layouts.main')

@section('title', $seo['title'] ?? 'خدمات منصة كيف التعليمية')

@section('content')
  <div id="fxbg"></div>
  <div class="pulse-gradient" aria-hidden="true"></div>

  {{-- Hero --}}
  <section class="hero" style="padding:6rem 0 3rem">
    <div class="container hero-grid" style="gap:2.5rem;">
      <div class="hero-copy" style="align-items: flex-start;">
        <h1>خدماتنا</h1>
        <p class="lead" style="max-width:60ch">
          في <strong>كيف التعليمية</strong> نضع بين يديك باقة شاملة من الخدمات الرقمية 
          التي تواكب احتياجات المعلم والطالب في الأردن والعالم العربي.  
          خدماتنا تجمع بين <strong>المحتوى الأكاديمي الموثوق</strong>، 
          <strong>الأدوات التفاعلية المبتكرة</strong>، و<strong>الدعم الأكاديمي والفني المتواصل</strong>.
        </p>
        <div class="cta-row">
          <a href="https://library.howplatform.net" class="btn btn-primary">استكشف المكتبة الرقمية</a>
          <a href="https://diploma.howplatform.net" class="btn btn-ghost">ابدأ رحلة الدبلوم العالي</a>
        </div>
      </div>
      <div class="blob" aria-hidden="true"></div>
    </div>
  </section>

  {{-- الخدمات الأساسية --}}
  <section>
    <div class="container">
      <h2 class="section-title">خدماتنا الأساسية</h2>
      <div class="grid-3">
        <div class="card">
          <h3>المحتوى الأكاديمي</h3>
          <p>ملخصات، نماذج مهام، ومواد تعليمية داعمة صُممت خصيصًا لطلبة الدبلوم العالي والمستويات الجامعية المختلفة.</p>
        </div>
        <div class="card">
          <h3>المكتبة الرقمية</h3>
          <p>أكبر مكتبة تعليمية عربية منظّمة حسب الصفوف والمباحث والفصول، مزوّدة بأدوات بحث ذكية وتجربة عرض سلسة.</p>
        </div>
        <div class="card">
          <h3>التدريب والدعم</h3>
          <p>دورات تدريبية وجلسات توجيهية يقدمها خبراء، بالإضافة إلى دعم فني متواصل للطلاب والمعلمين.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- خدمات إضافية --}}
  <section>
    <div class="container">
      <h2 class="section-title">خدمات إضافية</h2>
      <div class="grid-3">
        <div class="card">
          <h4>استشارات تربوية</h4>
          <p>خدمة إرشاد أكاديمي متخصصة لمساعدة الطلبة والمعلمين في تخطيط الدراسة، تطوير المهارات، وتحقيق أفضل النتائج.</p>
        </div>
        <div class="card">
          <h4>أدوات تفاعلية</h4>
          <p>منصات وأدوات رقمية تساعد على متابعة التقدّم الدراسي وتنظيم الوقت بمرونة وكفاءة عالية.</p>
        </div>
        <div class="card">
          <h4>مجتمع تعلّم</h4>
          <p>بناء مجتمع افتراضي تفاعلي يربط بين الطلاب والمعلمين لتبادل الخبرات والمعرفة في بيئة آمنة وداعمة.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- دعوة للتواصل --}}
  <section class="statistics">
    <div class="container" style="text-align:center">
      <h2 class="section-title">تواصل معنا</h2>
      <p class="lead" style="margin: 0 auto 1rem; max-width: 60ch;">
        هل ترغب بمعرفة المزيد أو التعاون معنا؟  
        فريقنا جاهز دائمًا للرد على استفساراتك وتقديم الدعم المناسب.
      </p>
      <div class="cta-row" style="justify-content:center">
        <a href="{{ url('/support') }}" class="btn btn-special">الدعم الفني</a>
        <a href="{{ url('/about') }}" class="btn btn-ghost">حول المنصة</a>
      </div>
    </div>
  </section>

  {{-- SEO JSON-LD --}}
  @push('scripts')
    <script type="application/ld+json">
      {
        "@context":"https://schema.org",
        "@type":"Service",
        "serviceType":"خدمات تعليمية رقمية",
        "provider":{
          "@type":"Organization",
          "name":"منصة كيف التعليمية",
          "url":"{{ $seo['url'] ?? url('/services') }}"
        }
      }
    </script>
  @endpush
@endsection
