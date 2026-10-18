@extends('layouts.app')
@section('title', $tool->name . ' | HOW Tools')

@section('content')
  {{-- مسار التنقّل --}}
  <nav aria-label="breadcrumb" style="margin-bottom:1rem; font-size:.9rem;">
    <a href="{{ route('tools.index') }}" style="color:var(--primary-600); text-decoration:none;">الأدوات</a>
    <span style="color:#999;"> / </span>
    <span>{{ $tool->name }}</span>
  </nav>

  <div class="page-header">
    <h1 class="page-title">{{ $tool->name }}</h1>
    @if($tool->description)
      <p class="page-description">{{ $tool->description }}</p>
    @endif
  </div>

  <div class="reveal" style="display:flex; gap:1.5rem; flex-wrap:wrap; align-items:flex-start;">
    @if($tool->image_url)
      <img src="{{ $tool->image_url }}" alt="{{ $tool->name }}"
           style="width:480px; max-width:100%; border-radius:16px; box-shadow:var(--shadow);">
    @endif

    <div style="flex:1; min-width:260px;">
      @if($tool->categories->isNotEmpty())
        <p><strong>التصنيفات:</strong>
          @foreach($tool->categories as $c)
            <a href="{{ route('tools.index', ['tab' => $c->slug]) }}" class="tool-category" style="text-decoration:none;">{{ $c->name }}</a>
          @endforeach
        </p>
      @endif

      @if(optional($tool->stats)->rating_avg && $tool->stats->rating_avg > 0)
        <p style="margin-top:.5rem;"><strong>التقييم:</strong>
          <i class="fas fa-star" style="color:#f5a623;"></i> {{ number_format($tool->stats->rating_avg,1) }}
          @if(optional($tool->stats)->rating_count)
            <span style="color:#999;">({{ (int)$tool->stats->rating_count }})</span>
          @endif
        </p>
      @endif

      @if(optional($tool->stats)->uses_count)
        <p style="margin-top:.25rem; color:#666;"><i class="fas fa-bolt"></i> استُخدمت {{ number_format((int)$tool->stats->uses_count) }} مرة</p>
      @endif

      <div style="margin-top:1.25rem;">
        <a href="{{ route('tools.go', $tool->slug) }}" class="btn btn-primary"
           @if($tool->url && $tool->url !== '#') target="_blank" rel="noopener" @endif>
          <i class="fas fa-arrow-up-right-from-square"></i> استخدم الأداة
        </a>
      </div>
    </div>
  </div>

  {{-- أدوات ذات صلة --}}
  @if($related->isNotEmpty())
    <div class="page-header" style="margin-top:2.5rem;">
      <h2 class="page-title" style="font-size:1.25rem;">أدوات ذات صلة</h2>
    </div>
    <div class="tools-grid">
      @foreach($related as $rt)
        <div class="tool-card-wrap" style="display:flex; flex-direction:column;">
          <a href="{{ route('tools.go', $rt->slug) }}" class="tool-card" style="display:block;" target="_blank" rel="noopener">
            <div class="tool-media">
              <img src="{{ $rt->image_url ?: 'https://via.placeholder.com/400x200/ffedd5/f97316?text='.urlencode($rt->name) }}" alt="{{ $rt->name }}" class="tool-image">
              <div class="tool-animation" style="background: {{ $rt->gradient ?: 'linear-gradient(45deg,#ffedd5,#fed7aa)' }}; display:flex; align-items:center; justify-content:center;">
                <i class="{{ $rt->icon_class ?: 'fas fa-cubes' }}" style="font-size:3rem; color: {{ $rt->icon_color ?: '#f97316' }};"></i>
              </div>
            </div>
            <div class="tool-content">
              <h3 class="tool-title">{{ $rt->name }}</h3>
              <p class="tool-description">{{ \Illuminate\Support\Str::limit($rt->description ?? '', 100) }}</p>
            </div>
          </a>
          <a href="{{ route('tools.show', $rt->slug) }}"
             style="display:inline-block; margin-top:.4rem; font-size:.85rem; color:var(--primary-600); text-decoration:none;">
            <i class="fas fa-circle-info"></i> تفاصيل الأداة
          </a>
        </div>
      @endforeach
    </div>
  @endif

  {{-- بيانات منظَّمة JSON-LD لمحركات البحث --}}
  @push('scripts')
  <script type="application/ld+json">
  {!! json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'SoftwareApplication',
    'name'     => $tool->name,
    'description' => (string) $tool->description,
    'applicationCategory' => 'EducationalApplication',
    'operatingSystem' => 'Web',
    'url'      => route('tools.show', $tool->slug),
    'image'    => $tool->image_url ?: null,
    'offers'   => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
    'aggregateRating' => (optional($tool->stats)->rating_count && $tool->stats->rating_count > 0) ? [
      '@type' => 'AggregateRating',
      'ratingValue' => round((float) $tool->stats->rating_avg, 1),
      'ratingCount' => (int) $tool->stats->rating_count,
    ] : null,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
  </script>
  @endpush
@endsection
