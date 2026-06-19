@props(['tool'])

@php
  $name   = $tool->name ?? 'اسم الأداة';
  $desc   = $tool->description ?? '';
  $image  = $tool->image_url ?: 'https://via.placeholder.com/400x200/ffedd5/f97316?text=' . urlencode($name);
  $grad   = $tool->gradient ?: 'linear-gradient(45deg, #ffedd5, #fed7aa)';
  $icon   = $tool->icon_class ?: 'fas fa-cubes';
  $icolor = $tool->icon_color ?: '#f97316';
  $rating = optional($tool->stats)->rating_avg;
  $cats   = $tool->relationLoaded('categories') ? $tool->categories : collect(); // Collection<Category>
@endphp

<a href="{{ route('tools.go', $tool->slug) }}" class="tool-card" style="display:block;">
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
        @php
          $max = 2;
          $shown = 0;
        @endphp
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
