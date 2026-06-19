{{-- resources/views/platforms.blade.php --}}
@extends('layouts.main')

@section('title', $seo['title'] ?? 'منصّاتنا')

@section('content')
  <div id="fxbg"></div>
  <div class="pulse-gradient" aria-hidden="true"></div>

  {{-- Hero --}}
  <section class="hero" style="padding:6rem 0 3rem">
    <div class="container hero-grid" style="gap:2.5rem;">
      <div class="hero-copy" style="align-items:flex-start">
        <h1>منصّاتنا</h1>
        <p class="lead" style="max-width:60ch">
          نطوّر منصّات تعليمية متخصصة تجمع المحتوى الرقمي والأدوات التفاعلية
          في مكان واحد، لخدمة الطلبة والمعلمين في الأردن والعالم العربي.
        </p>
        <div class="cta-row">
          <a href="https://library.howplatform.net" class="btn btn-primary">المكتبة الرقمية</a>
          <a href="https://diploma.howplatform.net" class="btn btn-ghost">الدبلوم العالي</a>
        </div>
      </div>
      <div class="blob" aria-hidden="true"></div>
    </div>
  </section>

  {{-- قائمة المنصّات --}}
  <section>
    <div class="container">
      <h2 class="section-title">استكشف المنصّات</h2>
      <div class="grid-3">
        @foreach($platforms as $p)
          <a class="card" style="text-decoration:none;" href="{{ $p['href'] }}" @if($p['href'] !== '#') target="_blank" rel="noopener" @endif>
            <h3>{{ $p['title'] }}</h3>
            <p>{{ $p['desc'] }}</p>
          </a>
        @endforeach
      </div>
    </div>
  </section>

  {{-- دعوة للفعل --}}
  <section class="statistics">
    <div class="container" style="text-align:center">
      <h2 class="section-title">هل لديك اقتراح لمنصّة جديدة؟</h2>
      <p class="lead" style="margin:0 auto 1rem; max-width:60ch;">
        نرحّب بالأفكار التي تخدم العملية التعليمية. أخبرنا بما تريد أن نطوّره لاحقًا.
      </p>
      <div class="cta-row" style="justify-content:center">
        <a href="{{ route('contact') }}" class="btn btn-special">تواصل معنا</a>
        <a href="{{ route('support') }}" class="btn btn-ghost">الدعم الفني</a>
      </div>
    </div>
  </section>

  {{-- SEO JSON-LD --}}
  @push('scripts')
    <script type="application/ld+json">
      {
        "@context":"https://schema.org",
        "@type":"CollectionPage",
        "name":"منصّاتنا",
        "url":"{{ $seo['url'] ?? url('/platforms') }}",
        "hasPart":[
          { "@type":"WebSite", "name":"منصة الدبلوم العالي", "url":"https://diploma.howplatform.net" },
          { "@type":"WebSite", "name":"المكتبة الرقمية", "url":"https://library.howplatform.net" }
        ]
      }
    </script>
  @endpush
@endsection
