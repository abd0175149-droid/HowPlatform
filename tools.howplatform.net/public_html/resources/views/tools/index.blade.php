@extends('layouts.app')
@section('title','الأدوات | HOW Tools')

@section('content')
  <div class="page-header">
    <h1 class="page-title">قائمة الأدوات</h1>
    <p class="page-description">استعرض الأدوات بحسب التصنيف أو الشُهرة أو الأحدث.</p>
  </div>

  {{-- تبويبات رئيسية (حسب طلبك) --}}
  @php
    $tabs = [
      'all'          => 'جميع الأدوات',
      'featured'     => 'الأدوات المميزة',
      'most-used'    => 'الأكثر استخداماً',
      'most-popular' => 'الأكثر شهرة',
      'new'          => 'الأدوات الجديدة',
    ];
  @endphp

  @push('styles')
  <style>
    @media (min-width: 992px) {
      .mobile-chips { display: none !important; }
    }
  </style>
  @endpush

  <div class="mobile-chips" style="margin-bottom:1rem; display:flex; gap:.5rem; flex-wrap:wrap;">
    @foreach($tabs as $key => $label)
      <a href="{{ route('tools.index', ['tab' => $key]) }}"
         class="tool-category"
         style="text-decoration:none; {{ $activeTab === $key ? 'background: var(--primary-600); color:#fff;' : '' }}">
        {{ $label }}
      </a>
    @endforeach
  </div>

  {{-- (اختياري) عرض التصنيفات الديناميكية من الجدول أيضاً --}}
  @if($categories->count())
    <div class="mobile-chips" style="margin-bottom:1rem; display:flex; gap:.35rem; flex-wrap:wrap;">
      @foreach($categories as $cat)
        <a href="{{ route('tools.index', ['tab' => $cat->slug]) }}"
           class="tool-category"
           style="text-decoration:none; {{ $activeTab === $cat->slug ? 'background: var(--primary-600); color:#fff;' : '' }}">
          {{ $cat->name }}
        </a>
      @endforeach
    </div>
  @endif

  {{-- الشبكة --}}
  <div class="tools-grid">
    @forelse($tools as $tool)
      @php
        $name   = $tool->name;
        $desc   = $tool->description ?? '';
        $image  = $tool->image_url ?: 'https://via.placeholder.com/400x200/ffedd5/f97316?text=' . urlencode($tool->name);
        $grad   = $tool->gradient ?: 'linear-gradient(45deg, #ffedd5, #fed7aa)';
        $icon   = $tool->icon_class ?: 'fas fa-cubes';
        $icolor = $tool->icon_color ?: '#f97316';
        $rating = optional($tool->stats)->rating_avg;
        $cats   = $tool->categories ?? collect();
      @endphp

      <a href="{{ $tool->url }}" class="tool-card" style="display:block;" target="_blank" rel="noopener">
        <div class="tool-media">
          <img src="{{ $image }}" alt="{{ $name }}" class="tool-image">
          <div class="tool-animation" style="background: {{ $grad }}; display:flex; align-items:center; justify-content:center;">
            <i class="{{ $icon }}" style="font-size:3rem; color: {{ $icolor }};"></i>
          </div>
        </div>

        <div class="tool-content">
          <h3 class="tool-title">{{ $name }}</h3>
          <p class="tool-description">{{ \Illuminate\Support\Str::limit($desc, 140) }}</p>

          <div class="tool-meta">
            <div>
              @php $max=2; $shown=0; @endphp
              @foreach($cats as $c)
                @break($shown >= $max)
                <span class="tool-category">{{ $c->name }}</span>
                @php $shown++; @endphp
              @endforeach
              @if($cats->count() > $max)
                <span class="tool-category">+{{ $cats->count() - $max }}</span>
              @endif
            </div>

            @if($rating && $rating > 0)
              <div class="tool-rating"><i class="fas fa-star"></i> {{ number_format($rating,1) }}</div>
            @endif
          </div>
        </div>
      </a>
    @empty
      <div class="no-results">لا توجد أدوات مطابقة حالياً.</div>
    @endforelse
  </div>

  {{-- الترقيم --}}
  <div class="pagination-wrap" style="margin-top: 16px;">
    {{ $tools->links() }}
  </div>
@endsection

