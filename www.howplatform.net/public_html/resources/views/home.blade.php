@extends('layouts.main')

@section('content')
    {{-- Hero --}}
    <section class="hero">
        <div class="container hero-grid">
            <div class="hero-copy">
                <h1 class="reveal">كيف التعليمية</h1>
                <h2 class="reveal">المنصة العربية الرائدة التي تُعيد صياغة مستقبل التعليم بالتقنية</h2>
                <p class="lead reveal" style="transition-delay:.12s">
                    نوفّر منظومة تعليمية متكاملة تجمع بين الحلول الرقمية والمحتوى العملي والخدمات المبتكرة. 
                    بدأنا رحلتنا بالدبلوم العالي لإعداد المعلمين، ونواصل التوسع لبناء أكبر بيئة تعليمية عربية داعمة للمعلم والطالب والمؤسسة.
                </p>
                <div class="reveal" style="transition-delay:.22s; display:flex; gap:12px; flex-wrap:wrap;">
                    <a class="btn btn-primary magnet" href="https://diploma.howplatform.net" rel="noopener">ابدأ رحلتك التعليمية الآن</a>
                    <a class="btn btn-ghost magnet" href="{{ url('/services') }}">تعرّف على خدماتنا</a>
                </div>
            </div>
            <div class="hero-art reveal" style="transition-delay:.18s">
                <div class="blob" aria-hidden="true"></div>
            </div>
        </div>
    </section>

    {{-- من نحن --}}
    <section class="about">
        <div class="container">
            <h2 class="section-title reveal">من نحن</h2>
            <p class="reveal" style="transition-delay:.1s; text-align:center; max-width:800px; margin-inline:auto;">
منصة كيف التعليمية فضاء عربي مبتكر يدمج التقنية بالتعلّم ليمنح المعلمين والطلبة تجربة تعليمية متجددة ترتقي بجودة الممارسة التربوية، وتسهم في بناء مجتمع معرفي عربي متقدّم يستند إلى أسس راسخة من الإتقان والإبداع والالتزام. فهي لا تكتفي بتقديم محتوى أو أدوات، بل تصنع بيئة متكاملة تُحفّز على الابتكار وتُعزز من دور المعلّم والطالب في رحلة تعلّم أكثر عمقًا وتأثيرًا، حيث يصبح التعليم رسالة متجددة تحمل في طياتها مسؤولية بناء المستقبل وتطويره.            </p>
            <p class="reveal" style="transition-delay:.18s; text-align:center">
                <a class="btn btn-ghost magnet" href="{{ url('/about') }}">اقرأ المزيد عنّا</a>
            </p>
        </div>
    </section>
    {{-- الإحصائيات --}}
    <section class="statistics">
        <div class="container">
            <h2 class="section-title reveal">إحصائيات المكتبة الرقمية</h2>
            <div class="stats-grid">
                @foreach (($stats ?? []) as $s)
                    <article class="stat-card reveal">
                        <div class="ring" data-target="{{ $s['target'] }}" data-duration="{{ $s['duration'] }}">
                            <div class="stat-number">0</div>
                        </div>
                        <div class="stat-label">{{ $s['label'] }}</div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
{{-- منصاتنا --}}
<section class="platforms">
  <div class="container">
    <h2 class="section-title reveal">منصاتنا التعليمية</h2>

    <div class="grid-2">
      @foreach(($platforms ?? []) as $p)
        <article class="card reveal tilt">
          @if(!empty($p['icon']))
            <i class="{{ $p['icon'] }}"></i>
          @endif

          <h3>{{ $p['title'] }}</h3>
          <p>{{ $p['desc'] }}</p>
          <br>

          <a class="btn {{ $p['btn'] ?? 'btn-primary' }} magnet"
             href="{{ $p['href'] }}"
             @if(Str::startsWith($p['href'], ['http://','https://'])) rel="noopener" @endif>
            {{ $p['cta'] ?? 'تعرّف أكثر' }}
          </a>
        </article>
      @endforeach
    </div>
  </div>
</section>

@endsection
