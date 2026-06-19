{{-- resources/views/contact.blade.php --}}
@extends('layouts.main')

@section('title', $seo['title'] ?? 'التواصل')

@section('content')
  <div id="fxbg"></div>
  <div class="pulse-gradient" aria-hidden="true"></div>

  {{-- Hero --}}
  <section class="hero" style="padding:6rem 0 3rem">
    <div class="container hero-grid" style="gap:2.5rem;">
      <div class="hero-copy" style="align-items:flex-start">
        <h1>تواصل معنا</h1>
        <p class="lead" style="max-width:60ch">
          يسعدنا تواصلك عبر القنوات الرسمية التالية. اختر الطريقة الأنسب لك وسنكون سعداء بخدمتك والرد على استفساراتك في أقرب وقت.
        </p>
        <div class="cta-row">
          <a href="mailto:support@howplatform.net" class="btn btn-primary">
            <i class="fas fa-envelope"></i> بريد الدعم: support@howplatform.net
          </a>
          <a href="tel:+962791405085" class="btn btn-ghost">
            <i class="fas fa-phone"></i> اتصال هاتفي
          </a>
        </div>
      </div>
      <div class="blob" aria-hidden="true"></div>
    </div>
  </section>

  {{-- قنوات التواصل الاجتماعي --}}
  <section>
    <div class="container">
      <h2 class="section-title">قنواتنا الرسمية</h2>

      <div class="grid-2">
        <a class="card" href="{{ $social['facebook'] }}" target="_blank" rel="noopener">
          <h3><i class="fab fa-facebook-square"></i> فيسبوك</h3>
          <p>تابع آخر الأخبار والتحديثات على صفحتنا الرسمية.</p>
        </a>

        <a class="card" href="{{ $social['telegram'] }}" target="_blank" rel="noopener">
          <h3><i class="fab fa-telegram"></i> تيليجرام</h3>
          <p>قناتنا الرسمية للتنبيهات السريعة والمحتوى المختصر.</p>
        </a>
      </div>

      <div class="grid-3" style="margin-top:24px">
        <a class="card" href="{{ $social['whatsapp'] }}" target="_blank" rel="noopener">
          <h3><i class="fab fa-whatsapp"></i> واتساب</h3>
          <p>راسلنا مباشرة عبر واتساب على الرقم:</p>
          <p style="direction: ltr;display: contents;">+962 78 255 0901</p>
        </a>

        <a class="card" href="https://library.howplatform.net" target="_blank" rel="noopener">
          <h3><i class="fas fa-book-open"></i> المكتبة الرقمية</h3>
          <p>توجّه مباشرةً إلى مكتبتنا الرقمية: موارد منظّمة حسب الصف والمبحث.</p>
        </a>

        <a class="card" href="https://diploma.howplatform.net" target="_blank" rel="noopener">
          <h3><i class="fas fa-graduation-cap"></i> منصة الدبلوم العالي</h3>
          <p>مواد ومهام وإرشادات برنامج الدبلوم العالي لإعداد المعلمين.</p>
        </a>
      </div>
    </div>
  </section>

  {{-- طبقة معلومات إضافية --}}
  <section class="statistics">
    <div class="container" style="text-align:center">
      <h2 class="section-title">روابط مهمة</h2>
      <div class="cta-row" style="justify-content:center">
        <a href="{{ route('support') }}" class="btn btn-special"><i class="fas fa-headset"></i> الدعم الفني</a>
        <a href="{{ url('/faq') }}" class="btn btn-ghost"><i class="fas fa-question-circle"></i> الأسئلة الشائعة</a>
        <a href="{{ url('/privacy') }}" class="btn btn-ghost"><i class="fas fa-user-shield"></i> سياسة الخصوصية</a>
        <a href="{{ url('/terms') }}" class="btn btn-ghost"><i class="fas fa-file-contract"></i> الشروط والأحكام</a>
      </div>
    </div>
  </section>

  {{-- SEO JSON-LD --}}
  @push('scripts')
    <script type="application/ld+json">
      {
        "@context": "https://schema.org",
        "@type": "ContactPage",
        "mainEntity": {
          "@type": "Organization",
          "name": "منصة كيف التعليمية | How Platform",
          "url": "{{ $seo['url'] ?? url('/contact') }}",
          "contactPoint": [{
            "@type": "ContactPoint",
            "contactType": "customer support",
            "email": "support@howplatform.net",
            "telephone": "+962782550901",
            "areaServed": "JO",
            "availableLanguage": ["ar","en"]
          }],
          "sameAs": [
            "{{ $social['facebook'] }}",
            "{{ $social['telegram'] }}",
            "{{ $social['whatsapp'] }}"
          ]
        }
      }
    </script>
  @endpush
@endsection
